<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    // Alegerea îngustă golește selecțiile largi rămase în urmă — altfel un
                    // grade_level ales anterior ar supraviețui invizibil în payload.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('grade_level', null);
                        $set('school_class_id', null);
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

                // CONFIRMAREA audienței, înainte de salvare: cine anume va vedea evenimentul, cu
                // clasele enumerate și numărul de elevi. Eticheta unei opțiuni descrie o INTENȚIE;
                // rezumatul arată CONSECINȚA — pe el se prinde alegerea greșită.
                Placeholder::make('audience_summary')
                    ->label(__('panel.forms.calendar_event.audience_summary_label'))
                    ->content(fn (Get $get): string => self::audienceSummary(
                        $get('visibility_scope'),
                        $get('grade_level'),
                        $get('school_class_id'),
                    )),

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

        if ($user instanceof User && ! $user->canPublishContent()) {
            return [CalendarEventScope::SchoolClass->value => CalendarEventScope::SchoolClass->getLabel()];
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
     * „Vor vedea: …" — audiența REZOLVATĂ: clasele vizate + numărul de elevi înmatriculați.
     * Public și static, ca fraza să fie testabilă direct, nu doar prin randarea formularului.
     */
    public static function audienceSummary(mixed $scope, mixed $gradeLevel, mixed $classId): string
    {
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
