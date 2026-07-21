<?php

namespace App\Filament\Resources\Terms\Schemas;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Formularul de semestru, STANDARDIZAT (cerința beneficiarului, 2026-07-21, aceeași logică ca
 * Discipline/Elevi/Clase): semestrul = (an școlar, număr) + interval — numărul se ALEGE (iar
 * numerele deja folosite în anul ales nici nu apar), denumirea NU se tastează (canonicul
 * „Semestrul I/II" e generat de model; denumirile custom istorice rămân), granițele de dată au
 * limitele anului chiar în calendar (nu doar la validare), iar semestrul „curent" e al
 * SISTEMULUI (sincronizat automat din intervale — doar afișat aici).
 */
class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.term.section_identity'))
                    ->description(__('panel.forms.term.section_identity_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('academic_year_id')
                            ->label(__('panel.fields.academic_year'))
                            // Anii ÎNCHIȘI nu primesc structură nouă (catalogul lor e înghețat) — nu apar
                            // în opțiuni; regula de server de mai jos prinde și un POST ocolit de UI.
                            ->relationship(
                                'academicYear',
                                'name',
                                fn (Builder $query): Builder => $query->whereNull('closed_at'),
                            )
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            // Anul e IDENTITATEA semestrului: mutarea lui la alt an ar târî după el mii de
                            // note/absențe în alt catalog. Se alege la creare, apoi rămâne fix (nici nu se
                            // mai salvează pe edit — un POST meșterit nu-l poate muta).
                            ->disabledOn('edit')
                            ->saved(fn (string $operation): bool => $operation !== 'edit')
                            ->default(fn (): ?int => request()->integer('an') ?: SchoolCalendar::currentYearId())
                            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                $year = is_numeric($value) ? AcademicYear::withTrashed()->find((int) $value) : null;

                                if ($year !== null && $year->isClosed()) {
                                    $fail(__('panel.validation.term.year_closed'));
                                }
                            }),
                        // Numărul se ALEGE, nu se tastează — iar numerele DEJA definite în anul
                        // ales nici nu apar în listă (unicitatea devine imposibilă din UI;
                        // dublura de server rămâne pentru POST forjat).
                        Select::make('number')
                            ->label(__('panel.forms.term.number'))
                            ->options(static fn (Get $get, ?Model $record): array => self::numberOptions($get, $record))
                            ->required()
                            ->native(false)
                            ->live()
                            // Un singur „Semestrul N" per an: două rânduri cu același număr ar face
                            // ambigue filtrele pe semestru și ordinea din rapoarte.
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $yearId = $get('academic_year_id') ?? ($record?->getAttribute('academic_year_id'));

                                    if (! is_numeric($yearId) || ! is_numeric($value)) {
                                        return;
                                    }

                                    $taken = Term::query()
                                        ->where('academic_year_id', (int) $yearId)
                                        ->where('number', (int) $value)
                                        ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                        ->exists();

                                    if ($taken) {
                                        $fail(__('panel.validation.term.number_taken'));
                                    }
                                },
                            ]),
                        // Denumirea NU se tastează: canonicul „Semestrul I/II" e generat de model
                        // din număr; o denumire custom istorică rămâne și e spusă onest.
                        Placeholder::make('nume_generat')
                            ->label(__('panel.forms.term.name'))
                            ->content(static function (Get $get, ?Model $record): string {
                                $number = $get('number');

                                if (is_numeric($number)) {
                                    $canonical = Term::canonicalName((int) $number) ?? (string) $number;

                                    $currentName = $record instanceof Term ? $record->getAttribute('name') : null;

                                    if ($record instanceof Term
                                        && is_string($currentName)
                                        && $currentName !== ''
                                        && $currentName !== Term::canonicalName((int) $record->getOriginal('number'))) {
                                        return $currentName.' — '.__('panel.forms.term.name_custom_kept', ['canonical' => $canonical]);
                                    }

                                    return $canonical.' — '.__('panel.forms.term.name_generated');
                                }

                                return (string) __('panel.forms.term.name_pending');
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make(__('panel.forms.term.section_interval'))
                    ->description(__('panel.forms.term.section_interval_hint'))
                    ->columns(2)
                    ->schema([
                        // Intervalul e OBLIGATORIU: din el se derivă semestrul unei note/absențe după
                        // dată. Limitele anului sunt puse CHIAR în calendar (minDate/maxDate) — o
                        // dată din afara anului nu se mai poate nici alege; validarea rămâne plasa.
                        DatePicker::make('starts_on')
                            ->label(__('panel.fields.starts_on'))
                            ->required()
                            ->minDate(fn (Get $get): ?Carbon => self::yearBound($get, 'start'))
                            ->maxDate(fn (Get $get): ?Carbon => self::yearBound($get, 'end'))
                            ->helperText(fn (Get $get): ?string => self::yearContextHint($get)),
                        DatePicker::make('ends_on')
                            ->label(__('panel.fields.ends_on'))
                            ->required()
                            ->afterOrEqual('starts_on')
                            ->minDate(fn (Get $get): ?Carbon => self::yearBound($get, 'start'))
                            ->maxDate(fn (Get $get): ?Carbon => self::yearBound($get, 'end'))
                            // La mutarea granițelor, evaluările existente se REALINIAZĂ automat la noile
                            // intervale (vezi TermObserver) — administratorul află aici, nu descoperă după.
                            ->helperText(fn (?Model $record): ?string => $record !== null
                                ? (string) __('panel.forms.term.realign_hint')
                                : null)
                            // Intervalul semestrului trebuie să încapă în anul-părinte și să nu se suprapună cu
                            // alt semestru al aceluiași an — altfel Term::forDate() devine ambiguu (audit M-3).
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $yearId = $get('academic_year_id') ?? ($record?->getAttribute('academic_year_id'));
                                    $startsOn = $get('starts_on');

                                    if (! $yearId || ! is_string($startsOn) || $startsOn === '' || ! is_string($value) || $value === '') {
                                        return;
                                    }

                                    $year = AcademicYear::find((int) $yearId);

                                    if ($year !== null && $year->starts_on !== null && $year->ends_on !== null
                                        && (Carbon::parse($startsOn)->lt($year->starts_on) || Carbon::parse($value)->gt($year->ends_on))) {
                                        $fail(__('panel.validation.term.outside_year'));

                                        return;
                                    }

                                    // Suprapunerea se verifică peste TOATE semestrele, indiferent de an:
                                    // anii școlari sunt secvențiali, iar Term::forDate (derivarea semestrului
                                    // din data notei/absenței) devine ambiguu la ORICE suprapunere, nu doar
                                    // în interiorul aceluiași an.
                                    $overlaps = Term::query()
                                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                        ->whereDate('starts_on', '<=', $value)
                                        ->whereDate('ends_on', '>=', $startsOn)
                                        ->exists();

                                    if ($overlaps) {
                                        $fail(__('panel.validation.term.overlap'));
                                    }
                                },
                            ]),
                        // is_current NU se editează manual: se derivă automat din intervalele de date
                        // (comanda app:sync-current-term + acțiunea de sincronizare din pagina Semestre).
                        // Toggle-ul e read-only ca să nu rupă invariantul „un singur semestru curent" (audit M-2).
                        Toggle::make('is_current')
                            ->label(__('panel.forms.term.is_current'))
                            ->helperText(__('panel.forms.term.is_current_hint'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Numerele DISPONIBILE în anul ales (1–4 minus cele deja definite), etichetate cu denumirea
     * canonică. La editare, numărul propriu rămâne selectabil.
     *
     * @return array<int, string>
     */
    private static function numberOptions(Get $get, ?Model $record): array
    {
        $yearId = $get('academic_year_id') ?? ($record?->getAttribute('academic_year_id'));

        $taken = is_numeric($yearId)
            ? Term::query()
                ->where('academic_year_id', (int) $yearId)
                ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                ->pluck('number')
                ->map(fn ($number): int => (int) $number)
                ->all()
            : [];

        $options = [];

        foreach ([1, 2, 3, 4] as $number) {
            if (! in_array($number, $taken, true)) {
                $options[$number] = Term::canonicalName($number) ?? (string) $number;
            }
        }

        return $options;
    }

    /** Granița anului ales (pentru minDate/maxDate) — null când anul nu are interval definit. */
    private static function yearBound(Get $get, string $edge): ?Carbon
    {
        $yearId = $get('academic_year_id');

        if (! is_numeric($yearId)) {
            return null;
        }

        $year = AcademicYear::query()->find((int) $yearId);

        if ($year === null || $year->starts_on === null || $year->ends_on === null) {
            return null;
        }

        [$spanStart, $spanEnd] = SchoolCalendar::yearSpan($year);

        return $edge === 'start' ? Carbon::parse($spanStart) : Carbon::parse($spanEnd);
    }

    /**
     * Contextul în care se aleg granițele: intervalul anului + semestrele deja definite ale lui —
     * ca administratorul să nu jongleze între două ecrane ca să evite suprapunerile.
     */
    private static function yearContextHint(Get $get): ?string
    {
        $yearId = $get('academic_year_id');

        if (! is_numeric($yearId)) {
            return null;
        }

        $year = AcademicYear::query()->find((int) $yearId);

        if ($year === null) {
            return null;
        }

        [$spanStart, $spanEnd] = SchoolCalendar::yearSpan($year);

        $siblings = Term::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('starts_on')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Term $term): string => sprintf(
                '%s: %s – %s',
                $term->name,
                $term->starts_on?->format('d.m.Y') ?? '?',
                $term->ends_on?->format('d.m.Y') ?? '?',
            ))
            ->implode(' · ');

        $hint = (string) __('panel.forms.term.year_span_hint', [
            'from' => $spanStart->format('d.m.Y'),
            'to' => $spanEnd->format('d.m.Y'),
        ]);

        return $siblings === '' ? $hint : $hint.' · '.$siblings;
    }
}
