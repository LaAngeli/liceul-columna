<?php

namespace App\Filament\Resources\Terms\Schemas;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    // Anii ÎNCHIȘI nu primesc structură nouă (catalogul lor e înghețat) — nu apar
                    // în opțiuni; regula de server de mai jos prinde și un POST ocolit de UI.
                    ->relationship(
                        'academicYear',
                        'name',
                        fn (Builder $query): Builder => $query->whereNull('closed_at'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
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
                TextInput::make('number')
                    ->label(__('panel.forms.term.number'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4)
                    ->required()
                    ->live(onBlur: true)
                    // Numele se propune singur din număr (Semestrul I/II...), doar dacă e gol —
                    // nu suprascrie o denumire deja aleasă.
                    ->afterStateUpdated(static function (mixed $state, Get $get, Set $set): void {
                        $suggested = self::suggestedName($state);

                        if ($suggested !== null && blank($get('name'))) {
                            $set('name', $suggested);
                        }
                    })
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
                TextInput::make('name')
                    ->label(__('panel.forms.term.name'))
                    ->placeholder(__('panel.forms.term.name_placeholder'))
                    ->required()
                    ->maxLength(255),
                // Intervalul e OBLIGATORIU: din el se derivă semestrul unei note/absențe după dată.
                DatePicker::make('starts_on')
                    ->label(__('panel.fields.starts_on'))
                    ->required()
                    ->helperText(fn (Get $get): ?string => self::yearContextHint($get)),
                DatePicker::make('ends_on')
                    ->label(__('panel.fields.ends_on'))
                    ->required()
                    ->afterOrEqual('starts_on')
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
                    ->dehydrated(false),
            ]);
    }

    /** „Semestrul I/II/III/IV" din numărul introdus; null pe valori în afara plajei. */
    private static function suggestedName(mixed $number): ?string
    {
        $numerals = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        return is_numeric($number) && isset($numerals[(int) $number])
            ? (string) __('panel.forms.term.name_suggestion', ['numeral' => $numerals[(int) $number]])
            : null;
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
