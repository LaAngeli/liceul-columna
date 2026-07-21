<?php

namespace App\Filament\Resources\CorigentaSessions\Schemas;

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionType;
use App\Models\AcademicYear;
use App\Models\CorigentaSession;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sesiunea de corigență, PROPUSĂ de sistem (standardizarea 2026-07-21): anul = cel curent (sau cel
 * din contextul navigatorului `?an=`), doar ani DESCHIȘI; sezonul propus după momentul din an;
 * perioada PRE-COMPLETATĂ pe fereastra convenției (spec §2.5) pentru fiecare combinație
 * sezon × tip și re-propusă la orice schimbare de context. Calendarul e MĂRGINIT la fereastra
 * anului școlar, sfârșitul nu poate fi ales înaintea începutului (minDate dinamic + golire la
 * mutarea începutului), iar dublurile (an, sezon, tip) și suprapunerile dintre sesiuni sunt
 * respinse și pe server. Invariantele absolute stau pe model ({@see CorigentaSession::booted}).
 *
 * Clasele/disciplinele NU se aleg aici: examenele per elev+disciplină se generează din mediile
 * sub 5 și se leagă de sesiune prin acțiunea „Leagă examenele" — sesiunea e doar cadrul (perioada).
 */
class CorigentaSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.forms.corigenta_session.section_context'))
                ->description(__('panel.forms.corigenta_session.section_context_hint'))
                ->columns(3)
                ->schema([
                    // Anul: implicit cel CURENT (sau cel cerut de navigator prin ?an=, validat);
                    // anii ÎNCHIȘI nu apar — catalogul lor e înghețat, nu mai are sesiuni de dat.
                    Select::make('academic_year_id')
                        ->label(__('panel.fields.academic_year'))
                        ->options(fn (?Model $record): array => self::openYearOptions($record))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => AcademicYear::withTrashed()->whereKey($value)->value('name'))
                        ->default(fn (): ?int => self::defaultYearId())
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::proposeDates($get, $set))
                        ->rules([
                            fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (! is_numeric($value)) {
                                    return;
                                }

                                $unchanged = $record instanceof CorigentaSession
                                    && (int) $record->getAttribute('academic_year_id') === (int) $value;

                                if (! $unchanged && AcademicYear::query()->whereKey((int) $value)->whereNotNull('closed_at')->exists()) {
                                    $fail(__('panel.validation.corigenta_session.year_closed'));
                                }
                            },
                        ]),
                    Select::make('season')
                        ->label(__('panel.forms.corigenta_session.season'))
                        ->options(CorigentaSeason::class)
                        ->default(fn (): string => self::defaultSeason()->value)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::proposeDates($get, $set)),
                    Select::make('type')
                        ->label(__('panel.forms.corigenta_session.type_long'))
                        ->options(CorigentaSessionType::class)
                        ->default(CorigentaSessionType::Baza->value)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::proposeDates($get, $set)),
                    self::contextInfoBox(),
                ]),

            Section::make(__('panel.forms.corigenta_session.section_period'))
                ->description(__('panel.forms.corigenta_session.section_period_hint'))
                ->columns(2)
                ->schema([
                    // Începutul: mărginit la fereastra anului școlar; propus pe fereastra
                    // convenției sezonului. La mutare, sfârșitul devenit invalid se golește.
                    DatePicker::make('starts_on')
                        ->label(__('panel.forms.corigenta_session.starts_on'))
                        ->required()
                        ->default(fn (): ?string => self::defaultDate(0))
                        ->minDate(fn (Get $get, ?Model $record): ?Carbon => self::windowBound($get, $record, 'min'))
                        ->maxDate(fn (Get $get, ?Model $record): ?Carbon => self::windowBound($get, $record, 'max'))
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                            $endsOn = $get('ends_on');

                            if (is_string($state) && $state !== '' && is_string($endsOn) && $endsOn !== '' && $endsOn < $state) {
                                $set('ends_on', null);
                            }
                        })
                        // Semnal, nu interdicție: o sesiune de „iarnă" programată vara e aproape
                        // sigur o alegere greșită de sezon — dar decizia finală rămâne a omului.
                        ->helperText(fn (Get $get): ?string => self::seasonWindowWarning($get))
                        ->rules([
                            fn (Get $get, ?Model $record): Closure => self::withinWindowRule($get, $record),
                        ]),
                    DatePicker::make('ends_on')
                        ->label(__('panel.forms.corigenta_session.ends_on'))
                        ->required()
                        ->default(fn (): ?string => self::defaultDate(1))
                        ->afterOrEqual('starts_on')
                        // Sfârșitul nu se poate nici SELECTA înaintea începutului ales.
                        ->minDate(fn (Get $get, ?Model $record): ?Carbon => self::endsOnMinDate($get, $record))
                        ->maxDate(fn (Get $get, ?Model $record): ?Carbon => self::windowBound($get, $record, 'max'))
                        ->live()
                        ->rules([
                            fn (Get $get, ?Model $record): Closure => self::withinWindowRule($get, $record),
                            // Dublura (an, sezon, tip) + suprapunerea cu altă sesiune — pe SERVER.
                            fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                if (self::comboTaken($get, $record)) {
                                    $fail(__('panel.validation.corigenta_session.duplicate'));

                                    return;
                                }

                                $overlap = self::overlappingSession($get, $record, $value);

                                if ($overlap !== null) {
                                    $fail(__('panel.validation.corigenta_session.overlap', [
                                        'session' => $overlap->season->getLabel().' · '.$overlap->type->getLabel()
                                            .' ('.$overlap->starts_on->format('d.m.Y').' – '.$overlap->ends_on->format('d.m.Y').')',
                                    ]));
                                }
                            },
                        ]),
                ]),
        ]);
    }

    /**
     * INFO BOX-ul de context: fluxul de aprobare (starea inițială = propunere), ferestrele
     * convenției pentru cele patru combinații și locul claselor/disciplinelor (examenele).
     */
    private static function contextInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->columnSpanFull()
            ->schema([
                Text::make(fn (): string => (string) __('panel.forms.corigenta_session.flow_info'))->weight('bold'),
                Text::make(fn (): string => (string) __('panel.forms.corigenta_session.windows_info')),
                Text::make(fn (): string => (string) __('panel.forms.corigenta_session.exams_info')),
            ]);
    }

    /**
     * Anii DESCHIȘI (cel curent primul); anul fișei editate rămâne în listă chiar dacă s-a închis
     * între timp — identitatea înregistrării nu se pierde.
     *
     * @return array<int, string>
     */
    private static function openYearOptions(?Model $record): array
    {
        $years = AcademicYear::query()
            ->whereNull('closed_at')
            ->orderByDesc('is_current')
            ->orderBy('starts_on')
            ->pluck('name', 'id')
            ->all();

        if ($record instanceof CorigentaSession) {
            $recordYearId = (int) $record->getAttribute('academic_year_id');

            if (! array_key_exists($recordYearId, $years)) {
                $name = AcademicYear::withTrashed()->whereKey($recordYearId)->value('name');

                if (is_string($name)) {
                    $years[$recordYearId] = $name;
                }
            }
        }

        return $years;
    }

    /** Anul implicit: cel cerut de navigator (`?an=`, doar dacă există și e deschis) sau cel curent. */
    private static function defaultYearId(): ?int
    {
        $raw = request()->query('an');

        if (is_string($raw) && ctype_digit($raw)
            && AcademicYear::query()->whereKey((int) $raw)->whereNull('closed_at')->exists()) {
            return (int) $raw;
        }

        $current = AcademicYear::query()->where('is_current', true)->whereNull('closed_at')->value('id');

        return $current !== null ? (int) $current : null;
    }

    /** Sezonul propus după momentul din an: oct–ian → iarnă (restanțele sem. I), altfel vară. */
    private static function defaultSeason(): CorigentaSeason
    {
        $month = SchoolCalendar::localNow()->month;

        return in_array($month, [10, 11, 12, 1], true) ? CorigentaSeason::Iarna : CorigentaSeason::Vara;
    }

    /** Data propusă implicit (index 0 = început, 1 = sfârșit) pentru contextul implicit complet. */
    private static function defaultDate(int $index): ?string
    {
        $window = self::proposedWindow(self::defaultYearId(), self::defaultSeason()->value, CorigentaSessionType::Baza->value);

        return $window[$index] ?? null;
    }

    /** La schimbarea anului/sezonului/tipului, perioada se RE-PROPUNE pe fereastra convenției. */
    private static function proposeDates(Get $get, Set $set): void
    {
        $window = self::proposedWindow($get('academic_year_id'), $get('season'), $get('type'));

        if ($window !== null) {
            $set('starts_on', $window[0]);
            $set('ends_on', $window[1]);
        }
    }

    /**
     * Fereastra PROPUSĂ de convenția spec §2.5 pentru (an, sezon, tip):
     * vară+bază = ultima săptămână din august; vară+repetată = începutul anului școlar următor;
     * iarnă+bază = vacanța de Crăciun; iarnă+repetată = prima săptămână a semestrului II
     * (din chiar intervalul semestrului, când e definit).
     *
     * @return array{0: string, 1: string}|null
     */
    private static function proposedWindow(mixed $yearId, mixed $season, mixed $type): ?array
    {
        $years = self::calendarYears($yearId);

        if ($years === null) {
            return null;
        }

        [$y1, $y2] = $years;
        $seasonValue = $season instanceof CorigentaSeason ? $season->value : $season;
        $typeValue = $type instanceof CorigentaSessionType ? $type->value : $type;

        if ($seasonValue === CorigentaSeason::Vara->value) {
            return $typeValue === CorigentaSessionType::Repetata->value
                ? [$y2.'-09-01', $y2.'-09-07']
                : [$y2.'-08-24', $y2.'-08-31'];
        }

        if ($seasonValue !== CorigentaSeason::Iarna->value) {
            return null;
        }

        if ($typeValue === CorigentaSessionType::Repetata->value) {
            $termStart = Term::query()
                ->where('academic_year_id', (int) $yearId)
                ->where('number', 2)
                ->value('starts_on');

            if ($termStart !== null) {
                $start = Carbon::parse((string) $termStart);

                return [$start->toDateString(), $start->copy()->addDays(6)->toDateString()];
            }

            return [$y2.'-01-10', $y2.'-01-17'];
        }

        return [$y1.'-12-23', $y1.'-12-30'];
    }

    /**
     * Fereastra PERMISĂ a anului: de la începutul lui până la 30 septembrie al celui de-al doilea
     * an calendaristic — acoperă și sesiunea repetată de vară (începutul anului școlar următor),
     * dar taie derapajele de tip „sesiune în 2030 pe anul 2025–2026".
     */
    private static function windowBound(Get $get, ?Model $record, string $edge): ?Carbon
    {
        $yearId = self::resolvedYearId($get, $record);
        $years = self::calendarYears($yearId);

        if ($years === null) {
            return null;
        }

        if ($edge === 'min') {
            $startsOn = AcademicYear::withTrashed()->whereKey($yearId)->value('starts_on');

            return $startsOn !== null ? Carbon::parse((string) $startsOn) : Carbon::parse($years[0].'-09-01');
        }

        return Carbon::parse($years[1].'-09-30');
    }

    /** minDate-ul sfârșitului: începutul ALES (dinamic), altfel granița ferestrei anului. */
    private static function endsOnMinDate(Get $get, ?Model $record): ?Carbon
    {
        $startsOn = $get('starts_on');

        if (is_string($startsOn) && $startsOn !== '') {
            return Carbon::parse($startsOn);
        }

        return self::windowBound($get, $record, 'min');
    }

    /** Regula de SERVER a ferestrei anului (dublura minDate/maxDate din calendar). */
    private static function withinWindowRule(Get $get, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $min = self::windowBound($get, $record, 'min');
            $max = self::windowBound($get, $record, 'max');

            if ($min === null || $max === null) {
                return;
            }

            $date = Carbon::parse($value);

            if ($date->lt($min) || $date->gt($max)) {
                $fail(__('panel.validation.corigenta_session.outside_year_window', [
                    'from' => $min->format('d.m.Y'),
                    'to' => $max->format('d.m.Y'),
                ]));
            }
        };
    }

    /** Mai există o sesiune cu aceeași combinație (an, sezon, tip)? Istoricul neatins e tolerat. */
    private static function comboTaken(Get $get, ?Model $record): bool
    {
        $yearId = self::resolvedYearId($get, $record);
        $season = $get('season');
        $type = $get('type');

        if ($yearId === null || $season === null || $season === '' || $type === null || $type === '') {
            return false;
        }

        $seasonValue = $season instanceof CorigentaSeason ? $season->value : (string) $season;
        $typeValue = $type instanceof CorigentaSessionType ? $type->value : (string) $type;

        if ($record instanceof CorigentaSession
            && (int) $record->getAttribute('academic_year_id') === $yearId
            && $record->season->value === $seasonValue
            && $record->type->value === $typeValue) {
            // Combinația fișei editate, neschimbată — nu e o dublură nouă.
            return false;
        }

        return CorigentaSession::query()
            ->where('academic_year_id', $yearId)
            ->where('season', $seasonValue)
            ->where('type', $typeValue)
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record?->getKey()))
            ->exists();
    }

    /** Sesiunea aceluiași an peste care s-ar suprapune intervalul ales — null când nu e niciuna. */
    private static function overlappingSession(Get $get, ?Model $record, mixed $endsOn): ?CorigentaSession
    {
        $yearId = self::resolvedYearId($get, $record);
        $startsOn = $get('starts_on');

        if ($yearId === null || ! is_string($startsOn) || $startsOn === '' || ! is_string($endsOn) || $endsOn === '') {
            return null;
        }

        return CorigentaSession::query()
            ->where('academic_year_id', $yearId)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record?->getKey()))
            ->first();
    }

    /** Avertisment MOALE: începutul ales cade în afara lunilor tipice sezonului ales. */
    private static function seasonWindowWarning(Get $get): ?string
    {
        $season = $get('season');
        $startsOn = $get('starts_on');

        if ($season === null || $season === '' || ! is_string($startsOn) || $startsOn === '') {
            return null;
        }

        $seasonValue = $season instanceof CorigentaSeason ? $season->value : (string) $season;
        $month = Carbon::parse($startsOn)->month;

        $typical = $seasonValue === CorigentaSeason::Iarna->value
            ? [12, 1, 2]
            : [6, 7, 8, 9];

        return in_array($month, $typical, true)
            ? null
            : (string) __('panel.forms.corigenta_session.season_window_warning');
    }

    /** Anul din starea formularului (creare) sau de pe fișă (editare). */
    private static function resolvedYearId(Get $get, ?Model $record): ?int
    {
        $yearId = $get('academic_year_id');

        if (! is_numeric($yearId) && $record instanceof CorigentaSession) {
            $yearId = $record->getAttribute('academic_year_id');
        }

        return is_numeric($yearId) ? (int) $yearId : null;
    }

    /**
     * Cei doi ani calendaristici ai anului școlar dat (din datele lui; numele canonic ca rezervă).
     *
     * @return array{0: int, 1: int}|null
     */
    private static function calendarYears(mixed $yearId): ?array
    {
        if (! is_numeric($yearId)) {
            return null;
        }

        $year = AcademicYear::withTrashed()->whereKey((int) $yearId)->first();

        if ($year === null) {
            return null;
        }

        // `getAttribute` + instanceof, nu proprietatea: phpdoc-ul/schema o dau drept certă,
        // dar anii legacy pot avea datele goale la runtime.
        $startsOn = $year->getAttribute('starts_on');
        $endsOn = $year->getAttribute('ends_on');

        $y1 = $startsOn instanceof Carbon
            ? $startsOn->year
            : AcademicYear::startYearFromName($year->getAttribute('name'));

        if ($y1 === null) {
            return null;
        }

        return [$y1, $endsOn instanceof Carbon ? $endsOn->year : $y1 + 1];
    }
}
