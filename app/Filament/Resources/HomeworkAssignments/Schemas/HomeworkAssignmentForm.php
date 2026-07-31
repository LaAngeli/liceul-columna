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
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
                    ->simple(
                        TextInput::make('url')
                            ->url()
                            ->placeholder('https://…')
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
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*://#i', $state) === 1 ? $state : 'https://'.$state;
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
     * Clasele-țintă ale rolului: alocările proprii (profesor) / anul curent (administrația).
     *
     * @return Collection<int, SchoolClass>
     */
    private static function targetableClasses(): Collection
    {
        $query = SchoolClass::query()
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section');

        if (($teacher = self::currentTeacher()) !== null) {
            $classIds = TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->distinct()
                ->pluck('school_class_id');

            $query->whereKey($classIds->all());
        } elseif (($yearId = Term::query()->where('is_current', true)->value('academic_year_id')) !== null) {
            $query->where('academic_year_id', $yearId);
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
