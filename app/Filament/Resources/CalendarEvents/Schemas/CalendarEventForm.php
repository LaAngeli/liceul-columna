<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Enums\AudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

class CalendarEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('panel.fields.type'))
                    ->options(CalendarEventType::options())
                    ->default(CalendarEventType::SchoolEvent->value)
                    ->native(false)
                    ->required(),

                Select::make('visibility_scope')
                    ->label(__('panel.forms.calendar_event.audience'))
                    ->options(fn (): array => self::scopeOptions())
                    ->default(fn (): string => self::defaultScope())
                    ->native(false)
                    ->live()
                    ->required()
                    // Alegerea unei audiențe golește selecțiile celorlalte rămase în urmă — altfel
                    // un grade_level (sau o listă de elevi) ales anterior ar supraviețui invizibil.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('grade_level', null);
                        $set('school_class_id', null);
                        $set('students', []);
                        $set('audience_reach', AudienceReach::Both->value);
                    }),

                Select::make('grade_level')
                    ->label(__('panel.forms.calendar_event.grade_level'))
                    ->options(fn (): array => self::gradeOptions())
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value),

                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value),

                // Cine, din familia fiecărui elev vizat, VEDE evenimentul — pus ÎNAINTEA selecției:
                // alegerea lui schimbă instrucțiunile de mai jos. (În calendar se aleg mereu ELEVI,
                // fiindcă evenimentul trăiește pe calendarul copilului; reach-ul decide cine din
                // familie îl vede — spre deosebire de Anunțuri, unde la „doar părinții" se aleg
                // părinți concreți.)
                Select::make('audience_reach')
                    ->label(__('panel.forms.calendar_event.reach'))
                    ->options(AudienceReach::options())
                    ->default(AudienceReach::Both->value)
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::Students->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::Students->value)
                    ->helperText(__('panel.forms.calendar_event.reach_hint')),

                // Elevii vizați — căutare pe SERVER, pe nume (nu listă statică de sute de opțiuni).
                // Salvarea relației (pivot) se face în paginile Create/Edit
                // ({@see CalendarEventResource::syncStudents}) — câmpul nu e coloană.
                Select::make('students')
                    ->label(__('panel.forms.calendar_event.students'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchStudents($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::studentLabels($values))
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::Students->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::Students->value)
                    // Gardă de SERVER înainte de persistare: dirigintele nu poate ținti elevi din
                    // afara claselor lui (un POST fabricat nu trebuie să creeze evenimentul întâi și
                    // să pice abia la sincronizare). Conducerea nu e restrânsă.
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $user = auth('web')->user();

                        if (! $user instanceof User || $user->canPublishContent()) {
                            return;
                        }

                        $ids = array_values(array_filter(array_map('intval', is_array($value) ? $value : [])));
                        $allowed = CalendarEventResource::homeroomStudentIds($user);

                        if (array_diff($ids, $allowed) !== []) {
                            $fail((string) __('panel.forms.calendar_event.students_out_of_scope'));
                        }
                    })
                    // Instrucțiunea urmează reach-ul ales mai sus (elev / părinți / ambii).
                    ->helperText(fn (Get $get): string => match ($get('audience_reach')) {
                        AudienceReach::Student->value => (string) __('panel.forms.calendar_event.students_hint_student'),
                        AudienceReach::Guardians->value => (string) __('panel.forms.calendar_event.students_hint_guardians'),
                        default => (string) __('panel.forms.calendar_event.students_hint_both'),
                    }),

                // CONFIRMAREA audienței, înainte de salvare: cine anume va vedea evenimentul, cu
                // clasele enumerate și numărul de elevi. Eticheta unei opțiuni descrie o INTENȚIE;
                // rezumatul arată CONSECINȚA — pe el se prinde alegerea greșită.
                Placeholder::make('audience_summary')
                    ->label(__('panel.forms.calendar_event.audience_summary_label'))
                    ->content(fn (Get $get): string => self::audienceSummary(
                        $get('visibility_scope'),
                        $get('grade_level'),
                        $get('school_class_id'),
                        $get('students'),
                        $get('audience_reach'),
                    )),

                // Comutatorul de notificare (implicit pornit): oprit → evenimentul doar apare în
                // calendar, fără să anunțe familiile. Util pentru intrări de rutină deja știute.
                Toggle::make('notify_families')
                    ->label(__('panel.forms.calendar_event.notify_families'))
                    ->default(true)
                    ->helperText(__('panel.forms.calendar_event.notify_families_hint')),

                TextInput::make('title')
                    ->label(__('panel.forms.calendar_event.title_ro'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('panel.forms.calendar_event.description_ro'))
                    ->rows(2)
                    ->maxLength(2000),

                DatePicker::make('starts_on')
                    ->label(__('panel.forms.calendar_event.starts'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    // Reperul e ZIUA DE AZI (în fusul școlii — serverul stochează UTC): formularul
                    // se deschide pe azi, iar zilele trecute sunt STINSE în calendar pentru cine nu
                    // are dreptul de înregistrare retroactivă. La editare, data ORIGINALĂ rămâne
                    // selectabilă chiar dacă e în trecut — corectarea titlului unui eveniment de
                    // ieri nu trebuie să oblige mutarea lui.
                    ->default(fn (): string => SchoolCalendar::localNow()->toDateString())
                    ->minDate(function (?CalendarEvent $record): ?string {
                        if (self::canBackdate()) {
                            return null;
                        }

                        $today = SchoolCalendar::localNow()->toDateString();
                        $original = $record?->starts_on?->toDateString();

                        return ($original !== null && $original < $today) ? $original : $today;
                    })
                    ->live()
                    ->required()
                    // Mutarea startului DUPĂ un sfârșit deja ales l-ar lăsa pe acesta în urmă —
                    // un interval care se încheie înainte să înceapă. Sfârșitul invalid se GOLEȘTE
                    // (eveniment de o zi, sensul documentat al câmpului gol), nu se mută tăcut pe
                    // o dată pe care utilizatorul n-a ales-o niciodată.
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $start = substr((string) $get('starts_on'), 0, 10);
                        $end = substr((string) $get('ends_on'), 0, 10);

                        if ($start !== '' && $end !== '' && $end < $start) {
                            $set('ends_on', null);
                        }
                    })
                    ->helperText(fn (): ?string => self::canBackdate()
                        ? (string) __('panel.forms.calendar_event.backdate_allowed')
                        : null)
                    // Pe SERVER, nu doar în calendarul vizual: un POST fabricat cu o dată din trecut
                    // trece de orice UI. Excepția de la editare: data neschimbată rămâne validă.
                    ->rule(fn (?CalendarEvent $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (self::canBackdate() || ! is_string($value)) {
                            return;
                        }

                        // Pe edit, starea vine ca datetime complet — se compară doar ZIUA.
                        $date = substr($value, 0, 10);
                        $today = SchoolCalendar::localNow()->toDateString();
                        $original = $record?->starts_on?->toDateString();

                        if ($date < $today && $date !== $original) {
                            $fail(__('panel.forms.calendar_event.past_date'));
                        }
                    }),

                DatePicker::make('ends_on')
                    ->label(__('panel.forms.calendar_event.ends'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    // STINSE vizual toate zilele dinaintea startului: un eveniment nu se poate
                    // încheia înainte să înceapă, deci calendarul nici nu le oferă. Fără start ales
                    // încă, reperul e azi (fusul școlii) — cu excepția dreptului de retro-datare.
                    ->minDate(function (Get $get): ?string {
                        $start = substr((string) $get('starts_on'), 0, 10);

                        if ($start !== '') {
                            return $start;
                        }

                        return self::canBackdate() ? null : SchoolCalendar::localNow()->toDateString();
                    })
                    // Dublura de SERVER a regulii vizuale: `afterOrEqual` e o regulă Laravel și
                    // pică la fel pe un POST fabricat care ocolește calendarul de selecție.
                    ->afterOrEqual('starts_on')
                    ->helperText(__('panel.forms.calendar_event.ends_hint')),

                TextInput::make('start_time')
                    ->label(__('panel.forms.calendar_event.start_time'))
                    ->placeholder(__('panel.forms.calendar_event.start_time_placeholder'))
                    ->rule('regex:/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->maxLength(5)
                    ->helperText(__('panel.forms.calendar_event.start_time_hint'))
                    // Pentru AZI, ora nu poate fi una deja trecută (fusul școlii); pentru zile
                    // viitoare orice oră e în regulă. Aceeași excepție la editare: ora originală
                    // a unui eveniment consumat rămâne validă cât timp nu e schimbată.
                    ->rule(fn (Get $get, ?CalendarEvent $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if (self::canBackdate() || ! is_string($value) || $value === '') {
                            return;
                        }

                        $now = SchoolCalendar::localNow();
                        // Pe edit, starea datei vine ca datetime complet — se compară doar ZIUA.
                        $date = substr((string) $get('starts_on'), 0, 10);

                        if ($date !== $now->toDateString()) {
                            return;
                        }

                        $unchanged = $record !== null
                            && $record->starts_on->toDateString() === $date
                            && $record->start_time === $value;

                        if (! $unchanged && $value < $now->format('H:i')) {
                            $fail(__('panel.forms.calendar_event.past_time'));
                        }
                    }),

                Repeater::make('translations')
                    ->relationship()
                    ->label(__('panel.forms.calendar_event.translations'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('panel.forms.calendar_event.language'))
                            ->options([
                                'ru' => __('panel.forms.calendar_event.language_ru'),
                                'en' => __('panel.forms.calendar_event.language_en'),
                            ])
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('title')
                            ->label(__('panel.forms.calendar_event.title'))
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('panel.forms.calendar_event.description'))
                            ->rows(2),
                    ])
                    ->itemLabel(fn (array $state): ?string => isset($state['locale']) ? strtoupper((string) $state['locale']) : null)
                    ->addActionLabel(fn (): string => __('panel.forms.calendar_event.add_translation'))
                    ->collapsed()
                    ->defaultItems(0),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function scopeOptions(): array
    {
        $user = auth('web')->user();

        // Dirigintele nu publică pe toată școala, dar poate ținti clasa lui SAU elevi anume din
        // clasele lui (audiența nominală, gardată în {@see CalendarEventPolicy}).
        if ($user instanceof User && ! $user->canPublishContent()) {
            return [
                CalendarEventScope::SchoolClass->value => CalendarEventScope::SchoolClass->getLabel(),
                CalendarEventScope::Students->value => CalendarEventScope::Students->getLabel(),
            ];
        }

        return CalendarEventScope::options();
    }

    private static function defaultScope(): string
    {
        $user = auth('web')->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            return CalendarEventScope::SchoolClass->value;
        }

        return CalendarEventScope::Global->value;
    }

    /**
     * Anii de studiu, fiecare cu CLASELE lui reale enumerate — „Anul V — clasele V 1, V 2", nu
     * „Treapta 5": „treaptă" înseamnă în limbajul școlii ciclul (primar/gimnaziu/liceu), iar aici
     * e vorba de un an de studiu; eticheta veche inducea exact confuzia pe care o gardăm.
     * Doar anul CURENT: un eveniment nou nu se adresează claselor din anii arhivați.
     *
     * @return array<int, string>
     */
    private static function gradeOptions(): array
    {
        $options = [];

        foreach (self::currentYearClasses()->groupBy('grade_level') as $grade => $classes) {
            $options[(int) $grade] = (string) __('panel.forms.calendar_event.grade_level_label', [
                'classes' => $classes
                    ->map(fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))
                    ->implode(', '),
            ]);
        }

        return $options;
    }

    /**
     * „Vor vedea: …" — audiența REZOLVATĂ: clasele vizate + numărul de elevi înmatriculați, sau,
     * pentru audiența nominală, numărul de elevi aleși + cine din familia lor. Public și static,
     * ca fraza să fie testabilă direct, nu doar prin randarea formularului.
     *
     * @param  mixed  $students  lista de id-uri de elevi (audiența nominală)
     * @param  mixed  $reach  {@see AudienceReach} value (elev/părinți/ambii)
     */
    public static function audienceSummary(mixed $scope, mixed $gradeLevel, mixed $classId, mixed $students = null, mixed $reach = null): string
    {
        if ($scope === CalendarEventScope::Students->value) {
            return self::nominalSummary($students, $reach);
        }

        $classes = self::currentYearClasses();

        if ($scope === CalendarEventScope::GradeLevel->value) {
            if (! is_numeric($gradeLevel)) {
                return (string) __('panel.forms.calendar_event.summary_pick_grade');
            }

            $classes = $classes->where('grade_level', (int) $gradeLevel);
        } elseif ($scope === CalendarEventScope::SchoolClass->value) {
            if (! is_numeric($classId)) {
                return (string) __('panel.forms.calendar_event.summary_pick_class');
            }

            $classes = $classes->where('id', (int) $classId);
        } elseif ($scope !== CalendarEventScope::Global->value) {
            return (string) __('panel.forms.calendar_event.summary_pick_scope');
        }

        if ($classes->isEmpty()) {
            return (string) __('panel.forms.calendar_event.summary_no_classes');
        }

        $students = Enrollment::query()
            ->whereIn('school_class_id', $classes->pluck('id'))
            ->distinct()
            ->count('student_id');

        $names = $scope === CalendarEventScope::Global->value
            ? (string) trans_choice('panel.forms.calendar_event.summary_all_classes', $classes->count(), ['count' => $classes->count()])
            : $classes->map(fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))->implode(', ');

        $summary = (string) trans_choice('panel.forms.calendar_event.summary_audience', $students, [
            'classes' => $names,
            'students' => $students,
        ]);

        // Personalul vede orice eveniment în panou — familiile sunt partea care variază.
        return $summary.' '.__('panel.forms.calendar_event.summary_staff_note');
    }

    /**
     * Rezumatul audienței nominale: câți elevi aleși + cine din familia lor îl vede.
     */
    private static function nominalSummary(mixed $students, mixed $reach): string
    {
        $ids = array_values(array_filter(is_array($students) ? $students : []));

        if ($ids === []) {
            return (string) __('panel.forms.calendar_event.summary_pick_students');
        }

        $reachCase = is_string($reach) ? AudienceReach::tryFrom($reach) : null;
        $reachLabel = ($reachCase ?? AudienceReach::Both)->getLabel();

        return (string) trans_choice('panel.forms.calendar_event.summary_students', count($ids), [
            'count' => count($ids),
            'reach' => mb_strtolower($reachLabel),
        ]);
    }

    /**
     * Căutare pe SERVER a elevilor audienței nominale, pe nume: elevii anului curent, etichetați
     * „Nume — Clasa". Dirigintele caută doar printre elevii claselor lui (aceeași gardă ca la
     * {@see classOptions()}) — zona de căutare urmează drepturile, nu doar textul tastat.
     *
     * @return array<int, string>
     */
    public static function searchStudents(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $user = auth('web')->user();
        $classes = self::currentYearClasses();

        if ($user instanceof User && ! $user->canPublishContent()) {
            $classes = $classes->whereIn('id', $user->homeroomSchoolClassIds());
        }

        if ($classes->isEmpty()) {
            return [];
        }

        $options = [];

        Enrollment::query()
            ->whereIn('school_class_id', $classes->pluck('id'))
            // Fiecare cuvânt tastat trebuie să se regăsească în nume SAU prenume — acoperă și
            // căutarea „Nume Prenume" completă, portabil (fără CONCAT, absent în SQLite-ul testelor).
            ->whereHas('student', function ($student) use ($search): void {
                foreach (preg_split('/\s+/', $search) ?: [] as $word) {
                    $student->where(fn ($inner) => $inner
                        ->where('last_name', 'like', "%{$word}%")
                        ->orWhere('first_name', 'like', "%{$word}%"));
                }
            })
            ->with(['student', 'schoolClass'])
            ->limit(50)
            ->get()
            ->each(function (Enrollment $enrollment) use (&$options): void {
                if ($enrollment->student === null) {
                    return;
                }

                $class = $enrollment->schoolClass;
                $classLabel = $class !== null ? trim($class->name.' '.($class->section ?? '')) : '';
                $options[$enrollment->student->id] = $classLabel !== ''
                    ? $enrollment->student->full_name.' — '.$classLabel
                    : $enrollment->student->full_name;
            });

        asort($options);

        return $options;
    }

    /**
     * Etichetele elevilor DEJA selectați (la editare) — pot fi în afara listei filtrate (ex. mutați
     * între clase), deci se rezolvă direct pe id, nu doar din {@see studentOptions()}.
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    private static function studentLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map('intval', $values)));

        if ($ids === []) {
            return [];
        }

        return Student::query()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => $student->full_name])
            ->all();
    }

    /** @return Collection<int, SchoolClass> */
    private static function currentYearClasses(): Collection
    {
        $yearId = SchoolCalendar::currentYearId();

        return SchoolClass::query()
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();
    }

    private static function canBackdate(): bool
    {
        return auth('web')->user()?->canBackdateCalendarEvents() ?? false;
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');
        $user = auth('web')->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            $query->whereKey($user->homeroomSchoolClassIds());
        }

        $options = [];

        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }
}
