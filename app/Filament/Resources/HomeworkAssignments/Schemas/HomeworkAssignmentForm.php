<?php

namespace App\Filament\Resources\HomeworkAssignments\Schemas;

use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\ContentTranslator;
use App\Support\WebLink;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Formularul temei — refăcut (2026-07-15): clasa se alege dintr-un SINGUR câmp cu ținte REALE
 * (nu treaptă + literă combinabile liber), iar disciplina e în cascadă strictă pe alocările
 * profesorului în clasa aleasă. Administrația primește în plus țintele „Toată treapta N".
 * Protecția reală e pe server ({@see EnforcesHomeworkScope}) — aici e UX-ul.
 */
class HomeworkAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Ținta temei: `class:{id}` (clasă reală) sau `grade:{n}` (toată treapta, doar
                // administrația). Profesorul vede DOAR clasele din alocările proprii.
                // Dirigintele-NU-autor (corecția directă) editează DOAR conținutul: ținta,
                // disciplina și data lecției rămân ale autorului (blocate aici, ignorate pe server).
                Select::make('class_target')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classTargetOptions())
                    // Venind din navigatorul de catalog, contextul pre-completează formularul —
                    // DOAR dacă e printre țintele permise rolului (un id străin e ignorat).
                    ->default(fn (): ?string => self::requestedContextTarget())
                    ->searchable()
                    ->required(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation))
                    ->disabled(fn (?HomeworkAssignment $record, string $operation): bool => self::contentOnlyEdit($record, $operation))
                    ->dehydrated(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation))
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('subject_id', null)),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    // Cascadă pe țintă: profesorul — DOAR disciplinele pe care LE PREDĂ în clasa
                    // aleasă (perechile din alocări); administrația — disciplinele predate în
                    // țintă (fallback: toate, când ținta nu are încă alocări).
                    ->options(fn (Get $get): array => self::subjectOptionsFor(
                        ($target = $get('class_target')) !== null ? (string) $target : null,
                    ))
                    ->default(fn (): ?int => self::requestedContextSubjectId())
                    ->searchable()
                    ->required(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation))
                    ->disabled(fn (?HomeworkAssignment $record, string $operation): bool => self::contentOnlyEdit($record, $operation))
                    ->dehydrated(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation)),
                DatePicker::make('assigned_on')
                    ->label(__('panel.forms.homework.assigned_on'))
                    ->required(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation))
                    ->disabled(fn (?HomeworkAssignment $record, string $operation): bool => self::contentOnlyEdit($record, $operation))
                    ->dehydrated(fn (?HomeworkAssignment $record, string $operation): bool => ! self::contentOnlyEdit($record, $operation))
                    // Data lecției poate fi și în viitor (planificare) — digestul zilnic o preia
                    // în ziua respectivă. Decizie asumată, spre deosebire de note/absențe.
                    ->default(now()),
                // O temă fără subiect ȘI fără sarcină obligatorie e goală — cel puțin unul.
                Textarea::make('topic')
                    ->label(__('panel.forms.homework.topic'))
                    ->rows(2)
                    ->requiredWithout('required_task')
                    ->validationAttribute(__('panel.forms.homework.topic'))
                    ->columnSpanFull(),
                Textarea::make('required_task')
                    ->label(__('panel.forms.homework.required_task'))
                    ->rows(3)
                    ->requiredWithout('topic')
                    ->validationAttribute(__('panel.forms.homework.required_task'))
                    ->columnSpanFull(),
                Textarea::make('optional_task')
                    ->label(__('panel.forms.homework.optional_task'))
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('links')
                    ->label(__('panel.forms.homework.links'))
                    // `hint`, nu `helperText` (01.08.2026): indicația stă pe ACEEAȘI linie cu
                    // eticheta, în dreapta — nu mai adaugă un rând sub câmp. Cele două repeatere
                    // aveau împreună patru rânduri de text lung, care împingeau butoanele
                    // formularului sub linia de plutire. Exemplele rămân în placeholder.
                    ->hint(__('panel.forms.homework.links_hint'))
                    ->simple(
                        TextInput::make('url')
                            // Validare pe SERVER — NU `->url()`, care ar seta `type="url"` și ar
                            // lăsa browserul să blocheze cu mesajul lui generic („Please enter a
                            // URL", în engleză, fără îndrumare). Așa apare mesajul NOSTRU, localizat,
                            // care trimite un text obișnuit („Manualul digital, cap. 4") spre câmpul
                            // „Resurse tipărite" de mai jos.
                            //
                            // SCHEMA NU SE MAI CERE (cerința beneficiarului, 07.08.2026): „test.md"
                            // se acceptă ca atare. Regula `url` a Laravel o impunea, iar profesorul
                            // tasta de fiecare dată șapte caractere care nu-i spun nimic. Structura
                            // de domeniu rămâne verificată ({@see WebLink}), iar schema se adaugă
                            // la salvare — linkul stocat trebuie să fie ABSOLUT, altfel `href`-ul
                            // din cabinet s-ar rezolva relativ la pagină.
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! WebLink::isValid(is_string($value) ? $value : null)) {
                                    $fail(__('panel.forms.homework.link_invalid'));
                                }
                            })
                            ->placeholder(__('panel.forms.homework.link_placeholder'))
                            // Sincronizare la ieșirea din câmp, ca butonul „deschide" să vadă
                            // valoarea proaspăt introdusă (fără asta, la creare linkul nou nu ar
                            // avea încă state pe server când se randează acțiunea).
                            ->live(onBlur: true)
                            // Butonul care DESCHIDE linkul într-un tab nou — până acum linkul
                            // salvat trăia doar ca text editabil (nici pagină de vizualizare, nici
                            // coloană), deci nu se putea deschide din panou. Apare doar când
                            // câmpul are o valoare.
                            ->suffixAction(
                                Action::make('openLink')
                                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                    ->label(__('panel.forms.homework.open_link'))
                                    ->tooltip(__('panel.forms.homework.open_link'))
                                    ->url(fn (?string $state): ?string => self::openableUrl($state), shouldOpenInNewTab: true)
                                    ->visible(fn (?string $state): bool => filled($state)),
                            )
                    )
                    ->addActionLabel(fn (): string => __('panel.forms.homework.add_link'))
                    ->columnSpanFull(),
                // Resurse TIPĂRITE/fizice (text liber): manuale, culegeri, pagini — NU URL-uri.
                // Se afișează în cabinet ca chip-uri gri, lângă linkuri (aceeași linie).
                Repeater::make('printed_resources')
                    ->label(__('panel.forms.homework.printed_resources'))
                    ->hint(__('panel.forms.homework.printed_resources_hint'))
                    ->simple(
                        TextInput::make('reference')
                            ->maxLength(255)
                            ->placeholder(__('panel.forms.homework.printed_resources_placeholder'))
                    )
                    ->addActionLabel(fn (): string => __('panel.forms.homework.add_printed_resource'))
                    ->columnSpanFull(),
                // Fișiere ATAȘATE (fișe de lucru, prezentări, imagini) — al treilea fel de resursă,
                // lângă linkuri și resursele tipărite. Stocare pe discul PRIVAT, cu nume generate
                // aleator; numele original se păstrează separat (`storeFileNamesIn`) și e cel sub
                // care elevul vede și descarcă fișierul din cabinet, printr-o rută autentificată.
                FileUpload::make('attachments')
                    ->label(__('panel.forms.homework.attachments'))
                    ->hint(__('panel.forms.homework.attachments_hint'))
                    ->multiple()
                    ->disk('local')
                    ->directory('homework-attachments')
                    ->storeFileNamesIn('attachment_names')
                    ->maxFiles(5)
                    // 10 MB / fișier — sub plafonul Livewire de 12 MB, ca refuzul să fie mesajul
                    // nostru de validare, nu un 422 criptic din upload-ul temporar.
                    ->maxSize(10240)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                        'text/plain',
                    ])
                    ->downloadable()
                    // Fără previzualizare: la imagini/PDF FilePond ar ascunde NUMELE fișierului
                    // (limitare documentată a plugin-ului) — iar aici numele e informația.
                    ->previewable(false)
                    ->appendFiles()
                    ->columnSpanFull(),
                // Autorul (teacher_id + author_name) NU mai trece prin formular: se forțează pe
                // server la creare și nu se atinge la editare (EnforcesHomeworkScope).
            ]);
    }

    /**
     * URL-ul deschizabil al unui câmp de link: gol → null (acțiunea se ascunde); un link fără
     * schemă (date legacy, importate fără `http(s)://`) primește `https://`, altfel browserul
     * l-ar trata ca legătură relativă și „nu s-ar întâmpla nimic".
     */
    public static function openableUrl(?string $state): ?string
    {
        // Aceeași normalizare ca la salvare ({@see WebLink}): butonul „deschide" trebuie să ducă
        // exact unde va duce și linkul salvat. Rândurile vechi, scrise înainte de normalizare,
        // trec tot pe aici.
        return WebLink::normalize($state);
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /**
     * Editare „doar conținut": dirigintele clasei corectează tema ALTUI profesor (corecția
     * directă, 2026-07-31) — ținta/disciplina/data lecției rămân ale autorului. Autorul și
     * administrația păstrează formularul întreg.
     */
    private static function contentOnlyEdit(?HomeworkAssignment $record, string $operation): bool
    {
        if ($operation !== 'edit' || $record === null) {
            return false;
        }

        $user = auth('web')->user();

        if ($user === null || $user->isAdministrator()) {
            return false;
        }

        $teacher = $user->teacher;

        return $teacher === null || (int) $record->teacher_id !== (int) $teacher->id;
    }

    /**
     * Țintele de clasă permise rolului, ca un singur câmp:
     *  - profesor/diriginte: clasele din alocările PROPRII (tangențe directe — nu toată treapta,
     *    nu clase străine, nu combinații treaptă+literă inexistente);
     *  - administrația: clasele anului curent + „Toată treapta N" pentru treptele existente.
     *
     * @return array<string, string>
     */
    public static function classTargetOptions(): array
    {
        $options = [];

        foreach (self::targetableClasses() as $class) {
            $options['class:'.$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        if (self::currentTeacher() === null) {
            $levels = [];

            foreach (self::targetableClasses() as $class) {
                $levels[(int) $class->grade_level] = true;
            }

            foreach (array_keys($levels) as $level) {
                $options['grade:'.$level] = (string) __('panel.forms.homework.whole_grade', ['level' => $level]);
            }
        }

        return $options;
    }

    /**
     * Clasele-țintă ale rolului: alocările proprii (profesor) / toate (administrația) — în AMBELE
     * cazuri restrânse la ANUL CURENT.
     *
     * Anul se aplica până acum doar administrației, iar profesorul primea orice clasă în care a
     * avut vreodată o alocare. Cu un singur an școlar în bază diferența nu se vedea; de la trecerea
     * în 2026–2027 (07.08.2026) însemna că profesorul putea posta o temă într-o clasă care nu mai
     * există ca activă. Formularul e singura gardă: `EnforcesHomeworkScope` verifică perechea
     * (clasă, disciplină), nu anul.
     *
     * Fără revenire la „toate" când anul curent nu-i dă nimic — spre deosebire de navigatorul de
     * catalog, care doar RĂSFOIEȘTE. Aici se SCRIE: un profesor fără alocare în anul curent chiar
     * nu are unde posta, iar o listă goală e adevărul, nu o clasă din anul trecut.
     *
     * @return Collection<int, SchoolClass>
     */
    private static function targetableClasses(): Collection
    {
        $query = SchoolClass::query()
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section');

        if (($yearId = Term::query()->where('is_current', true)->value('academic_year_id')) !== null) {
            $query->where('academic_year_id', $yearId);
        }

        if (($teacher = self::currentTeacher()) !== null) {
            $classIds = TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->distinct()
                ->pluck('school_class_id');

            $query->whereKey($classIds->all());
        }

        return $query->get();
    }

    /**
     * Disciplinele selectabile pentru ținta aleasă — perechile stricte ale profesorului;
     * administrația vede disciplinele predate în țintă (fallback: toate).
     *
     * @return array<int, string>
     */
    public static function subjectOptionsFor(?string $target): array
    {
        $teacher = self::currentTeacher();
        $query = Subject::query()->orderBy('name');

        if ($teacher !== null) {
            $assignments = TeachingAssignment::query()->where('teacher_id', $teacher->id);

            if ($target !== null && str_starts_with($target, 'class:')) {
                $assignments->where('school_class_id', (int) substr($target, 6));
            }

            $query->whereKey($assignments->pluck('subject_id')->unique()->all());
        } elseif ($target !== null) {
            // Administrația: disciplinele predate în clasa / treapta aleasă (fallback: toate).
            $assignments = TeachingAssignment::query();

            if (str_starts_with($target, 'class:')) {
                $assignments->where('school_class_id', (int) substr($target, 6));
            } elseif (str_starts_with($target, 'grade:')) {
                $assignments->whereIn(
                    'school_class_id',
                    SchoolClass::query()->where('grade_level', (int) substr($target, 6))->pluck('id'),
                );
            }

            $subjectIds = $assignments->pluck('subject_id')->unique();

            if ($subjectIds->isNotEmpty()) {
                $query->whereKey($subjectIds->all());
            }
        }

        $options = [];

        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /** Ținta de context din navigator (?clasa=), acceptată doar dacă e printre țintele permise. */
    private static function requestedContextTarget(): ?string
    {
        $raw = request()->query('clasa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $target = 'class:'.(int) $raw;

        return array_key_exists($target, self::classTargetOptions()) ? $target : null;
    }

    /** Disciplina de context (?disciplina=), validată în cascada țintei cerute. */
    private static function requestedContextSubjectId(): ?int
    {
        $raw = request()->query('disciplina');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return array_key_exists($id, self::subjectOptionsFor(self::requestedContextTarget())) ? $id : null;
    }
}
