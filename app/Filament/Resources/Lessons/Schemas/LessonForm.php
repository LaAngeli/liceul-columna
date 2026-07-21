<?php

namespace App\Filament\Resources\Lessons\Schemas;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Observers\LessonObserver;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Slotul de orar, GHIDAT nu tastat (standardizarea 2026-07-21): clasa e singura alegere de
 * context — anul școlar e DERIVAT din ea (afișat informativ, impus de {@see LessonObserver});
 * ziua și numărul lecției se aleg doar dintre sloturile LIBERE ale clasei; disciplina doar dintre
 * cele valabile pe treaptă, cu alocările didactice ale clasei evidențiate; profesorul se
 * COMPLETEAZĂ AUTOMAT din alocarea didactică (clasă, disciplină) când e una singură. Orice regulă
 * de UI are dublură pe server, iar invariantele absolute stau pe model ({@see Lesson::booted}).
 */
class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.lesson.section_context'))
                    ->description(__('panel.forms.lesson.section_context_hint'))
                    ->schema([
                        // Clasa e alegerea de bază: anul, disciplinele posibile și sloturile libere
                        // decurg din ea. Doar clasele anilor DESCHIȘI — orarul unui an închis e
                        // structură arhivată (clasa istorică a fișei editate rămâne vizibilă).
                        Select::make('school_class_id')
                            ->label(__('panel.fields.class'))
                            ->options(fn (?Lesson $record): array => self::classOptions($record))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::classLabel($value))
                            // Din orarul unei clase (navigator), contextul pre-completează clasa —
                            // validat (id străin = ignorat), nu preluat orbește.
                            ->default(fn (): ?int => self::contextClass()?->getKey())
                            ->searchable()
                            ->required()
                            ->live()
                            // Alegerile dependente de clasa veche nu au de ce să rămână: disciplina
                            // și profesorul se refac, iar numărul de lecție doar dacă noul context
                            // îl arată ocupat.
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                $set('subject_id', null);
                                $set('teacher_id', null);
                                self::dropTakenPeriod($get, $set);
                            })
                            ->rules([
                                fn (?Lesson $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (self::movesIntoClosedYear($value, $record)) {
                                        $fail(__('panel.validation.lesson.class_year_closed'));
                                    }
                                },
                            ]),
                        self::contextInfoBox(),
                    ]),

                Section::make(__('panel.forms.lesson.section_schedule'))
                    ->description(__('panel.forms.lesson.section_schedule_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('day_of_week')
                            ->label(__('panel.forms.lesson.weekday'))
                            ->options(fn (?Lesson $record): array => self::dayOptions($record))
                            // Din grila săptămânală, celula liberă vine cu ziua gata aleasă (validată).
                            ->default(fn (): ?int => self::contextInt('zi', 1, 6))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::dropTakenPeriod($get, $set)),
                        Select::make('lesson_number')
                            ->label(__('panel.forms.lesson.period_with_no'))
                            // Doar sloturile LIBERE ale zilei: cele ocupate nu se pot nici alege —
                            // suprapunerea devine imposibilă din UI, nu doar respinsă la salvare.
                            ->options(fn (Get $get, ?Lesson $record): array => self::periodOptions($get, $record))
                            ->default(fn (): ?int => self::contextInt('lectie', 1, 8))
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get, ?Lesson $record): ?string => self::periodOptions($get, $record) === []
                                ? (string) __('panel.forms.lesson.slots_full')
                                : null)
                            // Dublura pe SERVER a filtrului din UI (POST forjat). Fără ea,
                            // suprapunerea ieșea ca eroare de constrângere — pagină, nu mesaj.
                            ->rule(fn (Get $get, ?Lesson $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                if (! is_numeric($value) || (int) $value < 1 || (int) $value > 8) {
                                    $fail(__('panel.validation.lesson.number_out_of_range'));

                                    return;
                                }

                                if (self::slotTaken($get, $record, (int) $value)) {
                                    $fail(__('panel.forms.lesson.slot_taken'));
                                }
                            }),
                    ]),

                Section::make(__('panel.forms.lesson.section_details'))
                    ->description(__('panel.forms.lesson.section_details_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('subject_id')
                            ->label(__('panel.fields.subject'))
                            // FĂRĂ `relationship()`, deliberat: pe un câmp `searchable()` relația
                            // preia rezolvarea opțiunilor și `options()` nu mai are efect — căutarea
                            // returna tot nomenclatorul (prins la verificarea live: „Chimie" apărea
                            // la clasa a II-a). Eticheta valorii salvate vine din
                            // `getOptionLabelUsing`, ca fișele istorice să se vadă la editare.
                            ->getOptionLabelUsing(fn (mixed $value): ?string => Subject::query()->whereKey($value)->value('name'))
                            // Doar disciplinele valabile pe treapta clasei, cu ALOCĂRILE DIDACTICE
                            // ale clasei grupate în frunte: nomenclatorul are câte o fișă per ciclu
                            // pentru zece denumiri, iar alegerea fișei altui ciclu a fost exact
                            // defectul care a afectat 219 din 507 lecții la import.
                            ->options(fn (Get $get): array => self::subjectOptions($get('school_class_id')))
                            ->helperText(fn (Get $get): ?string => $get('school_class_id') === null
                                ? (string) __('panel.forms.lesson.pick_class_first')
                                : null)
                            ->searchable()
                            ->required()
                            ->live()
                            // Profesorul disciplinei e dedus din alocarea didactică — completat
                            // automat când alocarea e una singură; la grupe (mai mulți), omul alege.
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                $set('teacher_id', self::soleAssignedTeacherId($get('school_class_id'), $state));
                            })
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (self::subjectOutsideGrade($get('school_class_id'), $value)) {
                                        $fail(__('panel.validation.lesson.subject_outside_grade'));
                                    }
                                },
                            ]),
                        Select::make('teacher_id')
                            ->label(__('panel.forms.lesson.teacher_short'))
                            ->relationship('teacher', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->live()
                            // Întâi conflictele (semnal), apoi proveniența (alocarea didactică).
                            // AVERTISMENT, nu respingere: același profesor în același interval la
                            // două clase e uneori real (grupe comasate între clase paralele).
                            ->helperText(fn (Get $get, ?Lesson $record): ?string => self::conflictWarning($get, $record, 'teacher_id', 'clash_teacher')
                                ?? self::teacherProvenance($get)),
                        TextInput::make('room')
                            ->label(__('panel.forms.lesson.room'))
                            ->maxLength(20)
                            // Sălile deja folosite în orar, ca sugestii — nu nomenclator impus.
                            ->datalist(fn (): array => self::knownRooms())
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get, ?Lesson $record): ?string => self::conflictWarning($get, $record, 'room', 'clash_room')),
                    ]),
            ]);
    }

    /**
     * INFO BOX-ul de context: anul școlar DERIVAT din clasă (nu e o alegere — clasa aparține deja
     * unui an) + natura orarului (tipar săptămânal pe întregul an, fără dimensiune de semestru).
     */
    private static function contextInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->schema([
                Text::make(function (Get $get, ?Lesson $record): string {
                    $classId = $get('school_class_id');

                    if (! is_numeric($classId)) {
                        $classId = $record?->getAttribute('school_class_id');
                    }

                    $yearName = is_numeric($classId)
                        ? SchoolClass::query()->whereKey((int) $classId)->first()?->academicYear()->withTrashed()->value('name')
                        : null;

                    return is_string($yearName)
                        ? (string) __('panel.forms.lesson.year_line', ['year' => $yearName])
                        : (string) __('panel.forms.lesson.year_pending');
                })->weight('bold'),
                Text::make(fn (): string => (string) __('panel.forms.lesson.weekly_pattern_info')),
            ]);
    }

    /**
     * Suprapunerea pe profesor sau pe sală în același interval — text de avertizare cu clasele
     * implicate, sau null când nu e nimic de semnalat.
     *
     * De ce avertisment și nu validare: două lecții pot împărți legitim profesorul (grupe comasate
     * din clase paralele) sau sala (jumătăți de clasă la aceeași disciplină). O interdicție ar bloca
     * orare corecte; tăcerea ar ascunde greșeli de tastare. Semnalul informează, omul decide.
     */
    private static function conflictWarning(Get $get, ?Lesson $record, string $field, string $key): ?string
    {
        $value = $get($field);
        $day = $get('day_of_week');
        $number = $get('lesson_number');
        $classId = $get('school_class_id');

        if ($value === null || $value === '' || $day === null || $day === '' || ! is_numeric($number)) {
            return null;
        }

        $yearId = is_numeric($classId)
            ? SchoolClass::query()->whereKey((int) $classId)->value('academic_year_id')
            : null;

        $clashes = Lesson::query()
            ->where($field, $value)
            ->where('day_of_week', $day)
            ->where('lesson_number', (int) $number)
            // Doar în anul clasei: un slot din alt an școlar nu se suprapune cu acesta.
            ->when($yearId !== null, fn (Builder $q): Builder => $q->where('academic_year_id', $yearId))
            ->when(is_numeric($classId), fn (Builder $q): Builder => $q->where('school_class_id', '!=', (int) $classId))
            ->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record?->getKey()))
            ->with('schoolClass')
            ->get();

        if ($clashes->isEmpty()) {
            return null;
        }

        $classes = $clashes
            ->map(fn (Lesson $lesson): string => trim(($lesson->schoolClass->name ?? '?').' '.($lesson->schoolClass->section ?? '')))
            ->unique()
            ->implode(', ');

        return (string) __('panel.forms.lesson.'.$key, ['classes' => $classes]);
    }

    /**
     * Proveniența profesorului, din alocările didactice ale perechii (clasă, disciplină):
     * completat automat (una), de ales între grupe (mai multe) sau de completat manual (niciuna).
     */
    private static function teacherProvenance(Get $get): ?string
    {
        $classId = $get('school_class_id');
        $subjectId = $get('subject_id');

        if (! is_numeric($classId) || ! is_numeric($subjectId)) {
            return null;
        }

        $assignments = self::assignments((int) $classId, (int) $subjectId);

        if ($assignments->isEmpty()) {
            return (string) __('panel.forms.lesson.teacher_unassigned');
        }

        if ($assignments->unique('teacher_id')->count() === 1) {
            return (string) __('panel.forms.lesson.teacher_assigned');
        }

        // Hartă separată (array), nu relația: phpdoc-ul cert al relației ar face nullsafe-ul
        // „inutil" pentru phpstan, dar un profesor arhivat chiar întoarce null la runtime.
        $teacherNames = Teacher::query()
            ->whereIn('id', $assignments->pluck('teacher_id'))
            ->get()
            ->mapWithKeys(fn (Teacher $teacher): array => [(int) $teacher->id => $teacher->full_name])
            ->all();

        $teachers = $assignments
            ->sortBy('english_group')
            ->map(function (TeachingAssignment $assignment) use ($teacherNames): string {
                $name = $teacherNames[(int) $assignment->teacher_id] ?? '?';

                return $assignment->english_group !== null
                    ? $name.' (gr. '.$assignment->english_group.')'
                    : $name;
            })
            ->unique()
            ->implode(', ');

        return (string) __('panel.forms.lesson.teacher_groups', ['teachers' => $teachers]);
    }

    /** Profesorul unic alocat perechii (clasă, disciplină) — null când sunt zero sau mai mulți (grupe). */
    private static function soleAssignedTeacherId(mixed $classId, mixed $subjectId): ?int
    {
        if (! is_numeric($classId) || ! is_numeric($subjectId)) {
            return null;
        }

        $teacherIds = self::assignments((int) $classId, (int) $subjectId)
            ->pluck('teacher_id')
            ->unique();

        return $teacherIds->count() === 1 ? (int) $teacherIds->first() : null;
    }

    /** @return Collection<int, TeachingAssignment> */
    private static function assignments(int $classId, int $subjectId): Collection
    {
        return TeachingAssignment::query()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->get();
    }

    /**
     * Clasele în care se poate programa: anii DESCHIȘI, grupate pe an (anul curent primul) când
     * există mai mulți — orarul unui an închis e arhivă. Clasa fișei editate rămâne mereu în listă.
     *
     * @return array<int|string, mixed>
     */
    private static function classOptions(?Lesson $record): array
    {
        $classes = SchoolClass::query()
            ->whereHas('academicYear', fn (Builder $q): Builder => $q->whereNull('closed_at'))
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get();

        $recordClassId = $record?->getAttribute('school_class_id');

        if ($recordClassId !== null && ! $classes->contains('id', (int) $recordClassId)) {
            $historic = SchoolClass::query()->whereKey((int) $recordClassId)->first();

            if ($historic !== null) {
                $classes->push($historic);
            }
        }

        // Hartă separată (array), nu relația: anul unei clase istorice poate fi ARHIVAT, iar
        // relația întoarce atunci null la runtime, oricât de cert ar fi phpdoc-ul ei.
        /** @var array<int, AcademicYear> $years */
        $years = AcademicYear::withTrashed()
            ->whereIn('id', $classes->pluck('academic_year_id'))
            ->get()
            ->mapWithKeys(fn (AcademicYear $year): array => [(int) $year->id => $year])
            ->all();

        $byYear = $classes
            ->sortBy([
                fn (SchoolClass $class): int => ($years[(int) $class->academic_year_id] ?? null)?->is_current === true ? 0 : 1,
                fn (SchoolClass $class): string => (string) (($years[(int) $class->academic_year_id] ?? null)?->name),
            ])
            ->groupBy(function (SchoolClass $class) use ($years): string {
                $name = ($years[(int) $class->academic_year_id] ?? null)?->name;

                return $name ?? '—';
            });

        if ($byYear->count() === 1) {
            return $byYear->first()
                ?->mapWithKeys(fn (SchoolClass $class): array => [$class->id => trim($class->name.' '.($class->section ?? ''))])
                ->all() ?? [];
        }

        return $byYear
            ->map(fn (Collection $group): array => $group
                ->mapWithKeys(fn (SchoolClass $class): array => [$class->id => trim($class->name.' '.($class->section ?? ''))])
                ->all())
            ->all();
    }

    /** Eticheta clasei pentru valori deja salvate (fișe istorice din afara listei curente). */
    private static function classLabel(mixed $value): ?string
    {
        $class = SchoolClass::query()->whereKey($value)->first();

        return $class !== null ? trim($class->name.' '.($class->section ?? '')) : null;
    }

    /**
     * Zilele de școală (luni–vineri — convenția tuturor orarelor reale); ziua unei fișe istorice
     * rămâne selectabilă la editare, ca fișa să nu-și piardă valoarea.
     *
     * @return array<int, string>
     */
    private static function dayOptions(?Lesson $record): array
    {
        $recordDay = $record?->getAttribute('day_of_week');

        return collect(Weekday::cases())
            ->filter(fn (Weekday $day): bool => $day->value <= 5
                || ($recordDay instanceof Weekday && $day === $recordDay))
            ->mapWithKeys(fn (Weekday $day): array => [$day->value => $day->getLabel()])
            ->all();
    }

    /**
     * Numerele de lecție LIBERE pe (clasă, zi) — cele ocupate nu apar. Fără context complet,
     * plaja întreagă 1–8 (filtrarea se strânge pe măsură ce contextul se alege).
     *
     * @return array<int, string>
     */
    private static function periodOptions(Get $get, ?Lesson $record): array
    {
        $classId = $get('school_class_id');
        $day = $get('day_of_week');

        $taken = [];

        if (is_numeric($classId) && $day !== null && $day !== '') {
            $taken = Lesson::query()
                ->where('school_class_id', (int) $classId)
                ->where('day_of_week', $day)
                ->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record?->getKey()))
                ->pluck('lesson_number')
                ->all();
        }

        $options = [];

        foreach (range(1, 8) as $number) {
            if (! in_array($number, $taken, true)) {
                $options[$number] = (string) $number;
            }
        }

        return $options;
    }

    /** La schimbarea clasei sau a zilei, numărul de lecție rămâne doar dacă noul context îl arată liber. */
    private static function dropTakenPeriod(Get $get, Set $set): void
    {
        $number = $get('lesson_number');

        if (is_numeric($number) && self::slotTaken($get, null, (int) $number)) {
            $set('lesson_number', null);
        }
    }

    /** Există deja un alt slot pe aceeași (clasă, zi, nr. lecție)? */
    private static function slotTaken(Get $get, ?Lesson $record, int $lessonNumber): bool
    {
        $classId = $get('school_class_id');
        $day = $get('day_of_week');

        if (! is_numeric($classId) || $day === null || $day === '') {
            return false;
        }

        return Lesson::query()
            ->where('school_class_id', (int) $classId)
            ->where('day_of_week', $day)
            ->where('lesson_number', $lessonNumber)
            ->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record?->getKey()))
            ->exists();
    }

    /**
     * Disciplinele valabile pe treapta clasei alese, cu alocările didactice ale clasei grupate în
     * frunte (acolo e aproape întotdeauna alegerea corectă). Fără clasă — listă goală, nu tot
     * nomenclatorul: o listă completă invită exact la alegerea greșită.
     *
     * @return array<int|string, mixed>
     */
    private static function subjectOptions(mixed $classId): array
    {
        if (! is_numeric($classId)) {
            return [];
        }

        $grade = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');

        if ($grade === null) {
            return [];
        }

        $onGrade = Subject::query()
            ->where(fn (Builder $q): Builder => $q->whereNull('min_grade')->orWhere('min_grade', '<=', $grade))
            ->where(fn (Builder $q): Builder => $q->whereNull('max_grade')->orWhere('max_grade', '>=', $grade))
            ->orderBy('name')
            ->pluck('name', 'id');

        $assignedIds = TeachingAssignment::query()
            ->where('school_class_id', (int) $classId)
            ->distinct()
            ->pluck('subject_id')
            ->all();

        $assigned = $onGrade->only($assignedIds);

        if ($assigned->isEmpty()) {
            return $onGrade->all();
        }

        $others = $onGrade->except($assignedIds);

        $options = [(string) __('panel.forms.lesson.subjects_assigned') => $assigned->all()];

        if ($others->isNotEmpty()) {
            $options[(string) __('panel.forms.lesson.subjects_other')] = $others->all();
        }

        return $options;
    }

    /** Disciplina NU acoperă treapta clasei — dublura pe server a filtrului de opțiuni. */
    private static function subjectOutsideGrade(mixed $classId, mixed $subjectId): bool
    {
        if (! is_numeric($classId) || ! is_numeric($subjectId)) {
            return false;
        }

        $grade = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');
        $subject = Subject::query()->whereKey((int) $subjectId)->first();

        if ($grade === null || $subject === null) {
            return false;
        }

        $min = $subject->getAttribute('min_grade');
        $max = $subject->getAttribute('max_grade');

        return ($min !== null && (int) $min > $grade) || ($max !== null && (int) $max < $grade);
    }

    /**
     * Mutarea (sau crearea) într-o clasă al cărei an școlar e ÎNCHIS — dublura pe server a
     * filtrului din listă; fișa care rămâne pe clasa ei istorică nu e atinsă.
     */
    private static function movesIntoClosedYear(mixed $classId, ?Lesson $record): bool
    {
        if (! is_numeric($classId)) {
            return false;
        }

        if ($record !== null && (int) $record->getAttribute('school_class_id') === (int) $classId) {
            return false;
        }

        return SchoolClass::query()
            ->whereKey((int) $classId)
            ->whereHas('academicYear', fn (Builder $q): Builder => $q->whereNotNull('closed_at'))
            ->exists();
    }

    /**
     * Sălile deja folosite în orar — sugestii de completare, nu nomenclator impus.
     *
     * @return array<int, string>
     */
    private static function knownRooms(): array
    {
        return Lesson::query()
            ->whereNotNull('room')
            ->where('room', '!=', '')
            ->distinct()
            ->orderBy('room')
            ->pluck('room')
            ->values()
            ->all();
    }

    /** Un întreg din query string, doar dacă e în intervalul permis — altfel null, fără presupuneri. */
    private static function contextInt(string $key, int $min, int $max): ?int
    {
        $raw = request()->query($key);

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $value = (int) $raw;

        return ($value >= $min && $value <= $max) ? $value : null;
    }

    /** Clasa din contextul navigatorului (`?clasa=`), doar dacă există. */
    private static function contextClass(): ?SchoolClass
    {
        $raw = request()->query('clasa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        return SchoolClass::query()->whereKey((int) $raw)->first();
    }
}
