<?php

namespace App\Filament\Resources\Absences\Schemas;

use App\Enums\AbsenceStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\ContentTranslator;
use App\Support\Holidays;
use App\Support\PanelGuide;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class AbsenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    // Venind din navigatorul de catalog, contextul (clasa/disciplina) sosește în
                    // query string și pre-completează formularul — DOAR dacă e printre opțiunile
                    // permise rolului (un id străin e ignorat, nu preluat orbește).
                    ->default(fn (): ?int => self::requestedContextId('clasa', self::classOptions()))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('student_id', null);
                        $set('subject_id', null);
                        // Dirigenția e pe clasă: la schimbarea clasei, dreptul de a motiva pe loc se pierde.
                        $set('motivate_now', false);
                    }),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    // Doar disciplinele predate în clasa ALEASĂ (profesorul-pur: doar ale lui din acea clasă).
                    ->options(fn (Get $get): array => self::subjectOptions(
                        ($classId = $get('school_class_id')) !== null ? (int) $classId : null,
                    ))
                    // Disciplina din context se acceptă doar dacă e predată în clasa din context
                    // (aceeași cascadă ca opțiunile).
                    ->default(fn (): ?int => self::requestedContextId(
                        'disciplina',
                        self::subjectOptions(self::requestedContextId('clasa', self::classOptions())),
                    ))
                    ->searchable()
                    ->live()
                    // Obligatorie pentru profesorul-pur; dirigintele/administrația pot lăsa gol (absență pe zi întreagă).
                    ->required(fn (Get $get): bool => self::subjectRequired($get)),
                Select::make('student_id')
                    ->label(__('panel.fields.student'))
                    ->options(fn (Get $get): array => self::studentOptions(
                        ($classId = $get('school_class_id')) !== null ? (int) $classId : null,
                    ))
                    ->searchable()
                    ->required(),
                // Semestrul NU se alege manual: se derivă din `occurred_on` pe server (EnforcesAbsenceScope).
                DatePicker::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->hint(PanelGuide::hint('absence_occurred_on'))
                    ->required()
                    ->default(now())
                    // O absență nu poate fi în viitor; data determină și semestrul. Mesaj clar
                    // (fără ora cu secunde din `now()`) — vezi validation.not_future_date.
                    ->maxDate(now())
                    ->live()
                    // Avertisment, NU blocaj: o absență într-o zi liberă e aproape sigur o greșeală
                    // de tastare, dar recuperările/activitățile din zilele libere există (§ orare).
                    ->helperText(function (Get $get): ?string {
                        $value = $get('occurred_on');

                        if ($value === null) {
                            return null;
                        }

                        $holiday = Holidays::holidayOn(Carbon::parse(substr((string) $value, 0, 10)));

                        return $holiday === null
                            ? null
                            : (string) __('panel.forms.absence.free_day_warning', ['name' => $holiday->name]);
                    })
                    ->validationMessages(['before_or_equal' => __('validation.not_future_date')]),
                // ORA lecției (opțional) — identitatea care face posibile DOUĂ absențe la aceeași
                // disciplină în aceeași zi (două ore consecutive, 05.08.2026). Necompletat =
                // „ziua, fără oră precizată" — un singur asemenea slot pe zi.
                Select::make('lesson_number')
                    ->label(__('panel.forms.absence.lesson_number'))
                    ->helperText(__('panel.forms.absence.lesson_number_hint'))
                    ->options(array_combine(
                        range(1, 8),
                        array_map(fn (int $n): string => (string) __('panel.forms.absence.lesson_option', ['number' => $n]), range(1, 8)),
                    ))
                    ->placeholder(__('panel.forms.absence.lesson_unspecified')),
                // STATUTUL absenței — DOAR pentru cine îl poate fixa (dirigintele clasei alese /
                // administrația). Profesorul consemnează FĂRĂ statut („Absent"): el rareori știe de
                // ce lipsește elevul; dirigintele află pe parcursul zilei și decide (04.08.2026).
                // Câmp virtual (`status`), tradus în `is_motivated` pe server — CreateAbsence/EditAbsence.
                ToggleButtons::make('status')
                    ->label(__('panel.forms.absence.status'))
                    ->hint(PanelGuide::hint('absence_status'))
                    ->options(AbsenceStatus::class)
                    ->colors([
                        AbsenceStatus::Pending->value => 'warning',
                        AbsenceStatus::Motivated->value => 'success',
                        AbsenceStatus::Unmotivated->value => 'danger',
                    ])
                    ->icons([
                        AbsenceStatus::Pending->value => AbsenceStatus::Pending->icon(),
                        AbsenceStatus::Motivated->value => AbsenceStatus::Motivated->icon(),
                        AbsenceStatus::Unmotivated->value => AbsenceStatus::Unmotivated->icon(),
                    ])
                    ->inline()
                    ->default(AbsenceStatus::Pending->value)
                    // La creare, calea „Motivează acum" (cu dovadă) preia decizia — statutul simplu
                    // se ascunde ca să nu existe două răspunsuri la aceeași întrebare.
                    ->visible(fn (string $operation, Get $get): bool => self::canMotivate($get)
                        && ! ($operation === 'create' && (bool) $get('motivate_now'))),
                // Profesorului i se SPUNE regula, nu i se ascunde doar câmpul: altfel ar căuta
                // vechile butoane Mot./Nem. și ar crede că e o defecțiune.
                Text::make(fn (): string => (string) __('panel.forms.absence.status_teacher_note'))
                    ->color('gray')
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create'
                        && $get('school_class_id') !== null
                        && $get('school_class_id') !== ''
                        && ! self::canMotivate($get)),
                // Motivare LA CREARE (opțional, doar pe „create"): la activarea toggle-ului apar câmpurile
                // pentru motiv + dovadă. NU setează direct is_motivated pe absență — la salvare creează un
                // AbsenceMotivation aprobat cu justificativ (CreateAbsence::afterCreate). Sursă unică pentru
                // is_motivated. Câmpurile de motivare nu sunt coloane pe absences (se extrag în CreateAbsence).
                Toggle::make('motivate_now')
                    ->label(__('panel.forms.absence.motivate_now'))
                    ->helperText(__('panel.forms.absence.motivate_now_hint'))
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && self::canMotivate($get))
                    ->live(),
                Textarea::make('motivation_reason')
                    ->label(__('panel.fields.reason'))
                    ->rows(2)
                    ->maxLength(500)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && self::canMotivate($get) && (bool) $get('motivate_now'))
                    ->required(fn (Get $get): bool => (bool) $get('motivate_now')),
                FileUpload::make('motivation_document')
                    ->label(__('panel.tables.absences.motivate.document'))
                    ->helperText(__('panel.tables.absences.motivate.document_hint'))
                    ->disk('local')
                    ->directory('motivations')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(5120)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && self::canMotivate($get) && (bool) $get('motivate_now'))
                    ->required(fn (Get $get): bool => (bool) $get('motivate_now')),
                Hidden::make('teacher_id')
                    ->default(fn (): ?int => auth('web')->user()?->teacher?->id),
            ]);
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /**
     * Id-ul de context primit în query string (din navigatorul de catalog), acceptat DOAR dacă
     * face parte din opțiunile permise rolului — altfel null (fără pre-completare).
     *
     * @param  array<int, string>  $options
     */
    private static function requestedContextId(string $parameter, array $options): ?int
    {
        $raw = request()->query($parameter);

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return array_key_exists($id, $options) ? $id : null;
    }

    /**
     * Motivarea pe loc (cu dovadă) e a dirigintelui clasei alese sau a administrației — nu a
     * profesorului de disciplină. Aceeași regulă ca acțiunea „Motivează cu dovadă" din listă.
     */
    private static function canMotivate(Get $get): bool
    {
        $classId = ($c = $get('school_class_id')) !== null && $c !== '' ? (int) $c : null;

        if ($classId === null) {
            return false;
        }

        return auth('web')->user()?->canMotivateAbsencesFor($classId) ?? false;
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');

        // ANUL CURENT, pentru toate rolurile. Lipsea cu totul: cu un singur an în bază nu se vedea,
        // dar de la trecerea în 2026–2027 (07.08.2026) lista oferea și clasele anului încheiat —
        // adică se putea consemna într-un an închis. Formularul e garda: scoping-ul de pe server
        // verifică perechea (clasă, disciplină), nu anul.
        if (($yearId = Term::query()->where('is_current', true)->value('academic_year_id')) !== null) {
            $query->where('academic_year_id', $yearId);
        }

        if ($teacher = self::currentTeacher()) {
            $query->whereKey($teacher->contextSchoolClassIds(auth('web')->user()?->teachingContext())); // contextul pedagogic activ (F3)
        }

        $options = [];
        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Disciplinele selectabile. Când o clasă e aleasă, se restrâng la disciplinele PREDATE în acea
     * clasă (din teaching_assignments): profesorul-pur vede doar disciplinele LUI din clasa aleasă,
     * dirigintele/administrația pe toate cele predate în clasă. Fără nicio clasă aleasă, profesorul-pur
     * vede doar disciplinele lui. Așa dropdown-ul nu mai arată TOATE materiile (chiar și pt. diriginte).
     *
     * @return array<int, string>
     */
    private static function subjectOptions(?int $classId): array
    {
        $query = Subject::query()->orderBy('name');
        $teacher = self::currentTeacher();

        if ($classId !== null) {
            $subjectIds = TeachingAssignment::query()
                ->where('school_class_id', $classId)
                ->pluck('subject_id')
                ->unique();

            // Dirigintele CLASEI ALESE vede toate disciplinele ei (gestionează absențele clasei);
            // orice alt profesor (chiar diriginte la ALTĂ clasă) vede doar disciplinele LUI din clasă.
            if ($teacher !== null && ! in_array($classId, auth('web')->user()?->contextHomeroomClassIds() ?? [], true)) { // contextul pedagogic activ (F3)
                $ownIds = TeachingAssignment::query()
                    ->where('teacher_id', $teacher->id)
                    ->where('school_class_id', $classId)
                    ->pluck('subject_id');
                $subjectIds = $subjectIds->intersect($ownIds);
            }

            $query->whereKey($subjectIds->all());
        } elseif ($teacher !== null) {
            // Fără clasă aleasă încă: disciplinele contextului pedagogic activ (F3) — ale profesorului,
            // respectiv toate ale claselor de dirigenție. Se re-scopează la alegerea clasei.
            $query->whereKey(auth('web')->user()?->contextSubjectIds() ?? []);
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * Disciplina e obligatorie pentru profesorul-pur (consemnează absență la lecția LUI). Dirigintele
     * clasei alese și administrația pot lăsa gol = absență pe zi întreagă.
     */
    private static function subjectRequired(Get $get): bool
    {
        $teacher = self::currentTeacher();

        if ($teacher === null) {
            return false; // administrația academică — absență pe zi întreagă permisă
        }

        $classId = ($c = $get('school_class_id')) !== null ? (int) $c : null;

        if ($classId === null) {
            return true; // profesor, fără clasă aleasă → cere disciplina
        }

        return ! in_array($classId, auth('web')->user()?->contextHomeroomClassIds() ?? [], true); // contextul pedagogic activ (F3)
    }

    /**
     * Elevii selectabili. Dacă o clasă e aleasă în formular, lista se restrânge la elevii ei
     * (cascadă). Profesorul rămâne mereu limitat la clasele lui — chiar dacă forțează în formular
     * o clasă din afara scope-ului, intersecția dă listă goală.
     *
     * @return array<int, string>
     */
    private static function studentOptions(?int $schoolClassId = null): array
    {
        $query = Student::query()->orderBy('last_name')->orderBy('first_name');

        $teacher = self::currentTeacher();
        $classIds = null;

        if ($schoolClassId !== null) {
            $classIds = $teacher !== null
                ? array_values(array_intersect([$schoolClassId], $teacher->contextSchoolClassIds(auth('web')->user()?->teachingContext())))
                : [$schoolClassId];
        } elseif ($teacher !== null) {
            $classIds = $teacher->contextSchoolClassIds(auth('web')->user()?->teachingContext()); // contextul pedagogic activ (F3)
        }

        if ($classIds !== null) {
            $studentIds = Enrollment::query()
                ->whereIn('school_class_id', $classIds)
                ->pluck('student_id');
            $query->whereKey($studentIds);
        }

        $options = [];
        foreach ($query->get() as $student) {
            $options[$student->id] = $student->full_name;
        }

        return $options;
    }
}
