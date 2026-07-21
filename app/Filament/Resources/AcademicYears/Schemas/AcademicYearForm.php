<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use App\Models\AcademicYear;
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
 * Anul școlar, GENERAT nu tastat (cerința beneficiarului, 2026-07-21): denumirea se ALEGE dintre
 * candidații canonici („2026–2027" — următorii ani disponibili, cei definiți sunt excluși, deci
 * dublurile sunt imposibile din UI), sistemul propune implicit PRIMUL disponibil, iar datele se
 * pre-completează pe convenția școlii (01.09 → 30.06) cu ANUL CALENDARISTIC FIXAT din denumire:
 * utilizatorul ajustează doar ziua și luna — începutul aparține obligatoriu primului an
 * calendaristic, sfârșitul celui de-al doilea (impus în calendar prin minDate/maxDate ȘI pe
 * server). Toggle-ul „An curent" a DISPĂRUT: e oglinda semestrului curent, scrisă de sincronizarea
 * automată — vizibilă doar informativ în listă.
 *
 * Anul școlar e fundația tuturor configurărilor (semestre, clase, catalog) — de aici severitatea.
 */
class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.academic_year.section_identity'))
                    ->description(__('panel.forms.academic_year.section_identity_hint'))
                    ->schema([
                        // Denumirea se ALEGE dintre candidații canonici — nu se tastează. La
                        // EDITARE e identitatea anului: rămâne fixă (nici nu se salvează).
                        Select::make('name')
                            ->label(__('panel.forms.academic_year.name'))
                            ->options(static fn (?Model $record): array => $record instanceof AcademicYear
                                ? [$record->name => $record->name]
                                : AcademicYear::candidateNames())
                            ->default(static fn (): ?string => array_key_first(AcademicYear::candidateNames()))
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabledOn('edit')
                            ->saved(fn (string $operation): bool => $operation !== 'edit')
                            // La alegerea anului, perioada se RE-PROPUNE pe convenția școlii
                            // (01.09 → 30.06), pe anii calendaristici ai denumirii.
                            ->afterStateUpdated(static function (mixed $state, Set $set): void {
                                $startYear = AcademicYear::startYearFromName(is_string($state) ? $state : null);

                                if ($startYear !== null) {
                                    $set('starts_on', $startYear.'-09-01');
                                    $set('ends_on', ($startYear + 1).'-06-30');
                                }
                            })
                            // Dublura pe SERVER a canonicității + unicității (POST forjat).
                            ->rules([
                                static fn (?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if ($record !== null || ! is_string($value) || $value === '') {
                                        return;
                                    }

                                    if (AcademicYear::startYearFromName($value) === null) {
                                        $fail(__('panel.validation.academic_year.name_not_canonical'));

                                        return;
                                    }

                                    if (AcademicYear::withTrashed()->where('name', $value)->exists()) {
                                        $fail(__('panel.validation.academic_year.name_taken'));
                                    }
                                },
                            ]),
                        self::conventionInfoBox(),
                    ]),

                Section::make(__('panel.forms.academic_year.section_period'))
                    ->description(__('panel.forms.academic_year.section_period_hint'))
                    ->columns(2)
                    ->schema([
                        // Începutul: OBLIGATORIU în PRIMUL an calendaristic al denumirii — anul e
                        // fixat chiar în calendar; utilizatorul ajustează doar ziua și luna.
                        DatePicker::make('starts_on')
                            ->label(__('panel.fields.starts_on'))
                            ->required()
                            // Propunerea implicită vine COMPLETĂ: și datele, nu doar denumirea
                            // (default-ul selectului nu declanșează afterStateUpdated).
                            ->default(static fn (): ?string => self::defaultDate('start'))
                            ->minDate(fn (Get $get, ?Model $record): ?Carbon => self::calendarYearBound($get, $record, 'start', 'min'))
                            ->maxDate(fn (Get $get, ?Model $record): ?Carbon => self::calendarYearBound($get, $record, 'start', 'max'))
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $startYear = self::resolvedStartYear($get, $record);

                                    if ($startYear !== null && is_string($value) && $value !== ''
                                        && Carbon::parse($value)->year !== $startYear) {
                                        $fail(__('panel.validation.academic_year.starts_outside_first_year'));
                                    }
                                },
                            ]),
                        // Sfârșitul: OBLIGATORIU în AL DOILEA an calendaristic + fără suprapunere
                        // cu alt an școlar (anii sunt secvențiali — derivarea anului din orice dată
                        // trebuie să rămână neambiguă).
                        DatePicker::make('ends_on')
                            ->label(__('panel.fields.ends_on'))
                            ->required()
                            ->default(static fn (): ?string => self::defaultDate('end'))
                            ->afterOrEqual('starts_on')
                            ->minDate(fn (Get $get, ?Model $record): ?Carbon => self::calendarYearBound($get, $record, 'end', 'min'))
                            ->maxDate(fn (Get $get, ?Model $record): ?Carbon => self::calendarYearBound($get, $record, 'end', 'max'))
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    if (! is_string($value) || $value === '') {
                                        return;
                                    }

                                    $startYear = self::resolvedStartYear($get, $record);

                                    if ($startYear !== null && Carbon::parse($value)->year !== $startYear + 1) {
                                        $fail(__('panel.validation.academic_year.ends_outside_second_year'));

                                        return;
                                    }

                                    $startsOn = $get('starts_on');

                                    if (! is_string($startsOn) || $startsOn === '') {
                                        return;
                                    }

                                    // Anii școlari sunt SECVENȚIALI: o suprapunere ar face ambiguă derivarea
                                    // anului din orice dată (aceeași regulă ca la semestre, un nivel mai sus).
                                    $recordKey = $record?->getKey();

                                    $overlaps = AcademicYear::query()
                                        ->when($recordKey !== null, fn ($query) => $query->whereKeyNot($recordKey))
                                        ->whereNotNull('starts_on')
                                        ->whereNotNull('ends_on')
                                        ->whereDate('starts_on', '<=', $value)
                                        ->whereDate('ends_on', '>=', $startsOn)
                                        ->exists();

                                    if ($overlaps) {
                                        $fail(__('panel.validation.academic_year.overlap'));
                                    }
                                },
                            ]),
                    ]),
            ]);
    }

    /**
     * INFO BOX cu regula de structură + reperul existent (ultimul an definit) — contextul e
     * vizual, nu text mărunt sub câmpuri.
     */
    private static function conventionInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->schema([
                Text::make(fn (): string => (string) __('panel.forms.academic_year.convention_info'))->weight('bold'),
                Text::make(static function (): string {
                    $latest = AcademicYear::query()
                        ->whereNotNull('starts_on')
                        ->orderByDesc('starts_on')
                        ->first();

                    if ($latest === null) {
                        return (string) __('panel.forms.academic_year.no_years_info');
                    }

                    return (string) __('panel.forms.academic_year.latest_year_info', [
                        'year' => $latest->name,
                        'from' => $latest->starts_on?->format('d.m.Y') ?? '?',
                        'to' => $latest->ends_on?->format('d.m.Y') ?? '?',
                    ]);
                }),
            ]);
    }

    /**
     * Granițele calendaristice ale câmpului de dată: începutul trăiește în PRIMUL an al
     * denumirii, sfârșitul în AL DOILEA — anul e fixat, doar ziua/luna rămân de ales.
     */
    private static function calendarYearBound(Get $get, ?Model $record, string $field, string $edge): ?Carbon
    {
        $startYear = self::resolvedStartYear($get, $record);

        if ($startYear === null) {
            return null;
        }

        $calendarYear = $field === 'start' ? $startYear : $startYear + 1;

        return $edge === 'min'
            ? Carbon::parse($calendarYear.'-01-01')
            : Carbon::parse($calendarYear.'-12-31');
    }

    /** Datele propunerii implicite (convenția 01.09 → 30.06), pe anii primului candidat disponibil. */
    private static function defaultDate(string $field): ?string
    {
        $startYear = AcademicYear::startYearFromName(array_key_first(AcademicYear::candidateNames()));

        if ($startYear === null) {
            return null;
        }

        return $field === 'start' ? $startYear.'-09-01' : ($startYear + 1).'-06-30';
    }

    /** Anul calendaristic de start: din selecția curentă (creare) sau din numele recordului (editare). */
    private static function resolvedStartYear(Get $get, ?Model $record): ?int
    {
        $name = $get('name');

        if (! is_string($name) || $name === '') {
            $name = $record instanceof AcademicYear ? $record->name : null;
        }

        return AcademicYear::startYearFromName($name);
    }
}
