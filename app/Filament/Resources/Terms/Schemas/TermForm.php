<?php

namespace App\Filament\Resources\Terms\Schemas;

use App\Filament\Resources\Terms\Pages\CreateTerm;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Formularul de semestru, ca FLUX GHIDAT (cerința beneficiarului, 2026-07-21): utilizatorul
 * completează doar strictul necesar — anul (implicit cel curent), numărul (listă controlată,
 * numerele deja definite dispar) și intervalul (cu limitele anului chiar în calendar și cu
 * începutul propus automat pe prima zi liberă). Denumirea se completează automat din număr și
 * rămâne editabilă doar pentru cazuri justificate. Contextul (perioada anului + semestrele lui)
 * apare ca INFO BOX, nu ca text mărunt sub câmpuri. Switch-ul „Semestru curent" a DISPĂRUT din
 * formular: statutul e al sistemului (sincronizat automat din intervale — vizibil pe pagina
 * Semestre, doar informativ).
 *
 * Aceleași câmpuri sunt refolosite de wizard-ul paginii de creare ({@see CreateTerm})
 * și de layout-ul pe secțiuni al editării (configure) — o singură sursă pentru reguli.
 */
class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.term.step_year'))
                    ->description(__('panel.forms.term.step_year_hint'))
                    ->schema([
                        self::yearField(),
                        self::yearInfoBox(),
                    ]),
                Section::make(__('panel.forms.term.step_identity'))
                    ->description(__('panel.forms.term.step_identity_hint'))
                    ->columns(2)
                    ->schema([
                        self::numberField(),
                        self::nameField(),
                        self::yearFullInfoBox(),
                    ]),
                Section::make(__('panel.forms.term.step_period'))
                    ->description(__('panel.forms.term.step_period_hint'))
                    ->columns(2)
                    ->schema([
                        self::startsOnField(),
                        self::endsOnField(),
                        self::realignInfoBox(),
                    ]),
            ]);
    }

    /**
     * Anul școlar: implicit cel CURENT; anii ÎNCHIȘI nu primesc structură nouă (nu apar în
     * opțiuni; regula de server prinde și un POST ocolit de UI). Anul e IDENTITATEA semestrului:
     * mutarea lui la alt an ar târî după el mii de note/absențe în alt catalog — se alege la
     * creare, apoi rămâne fix (nici nu se mai salvează pe edit).
     */
    public static function yearField(): Select
    {
        return Select::make('academic_year_id')
            ->label(__('panel.fields.academic_year'))
            ->relationship(
                'academicYear',
                'name',
                fn (Builder $query): Builder => $query->whereNull('closed_at'),
            )
            ->preload()
            ->required()
            ->native(false)
            ->live()
            ->disabledOn('edit')
            ->saved(fn (string $operation): bool => $operation !== 'edit')
            ->default(fn (): ?int => request()->integer('an') ?: SchoolCalendar::currentYearId())
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                $year = is_numeric($value) ? AcademicYear::withTrashed()->find((int) $value) : null;

                if ($year !== null && $year->isClosed()) {
                    $fail(__('panel.validation.term.year_closed'));
                }
            });
    }

    /**
     * Contextul anului ales, ca INFO BOX (nu text mărunt): perioada anului — preluată automat din
     * configurația lui — și semestrele deja definite, ca granițele să se aleagă în cunoștință
     * de cauză. Apare doar după alegerea anului.
     */
    public static function yearInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->visible(fn (Get $get): bool => is_numeric($get('academic_year_id')))
            ->schema([
                Text::make(static function (Get $get): string {
                    $year = self::resolvedYear($get);

                    if ($year === null) {
                        return '';
                    }

                    [$spanStart, $spanEnd] = SchoolCalendar::yearSpan($year);

                    return (string) __('panel.forms.term.year_period_info', [
                        'year' => $year->name,
                        'from' => $spanStart->format('d.m.Y'),
                        'to' => $spanEnd->format('d.m.Y'),
                    ]);
                })->weight('bold'),
                Text::make(static function (Get $get): string {
                    $year = self::resolvedYear($get);

                    if ($year === null) {
                        return '';
                    }

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

                    return $siblings === ''
                        ? (string) __('panel.forms.term.year_no_terms_info')
                        : (string) __('panel.forms.term.year_terms_info', ['terms' => $siblings]);
                }),
            ]);
    }

    /**
     * Numărul: listă CONTROLATĂ — doar numerele încă nedefinite în anul ales (unicitatea devine
     * imposibilă din UI; dublura de server rămâne pentru POST forjat). La alegere, denumirea se
     * completează automat (dacă nu a fost editată justificat), iar începutul perioadei se propune
     * pe prima zi liberă a anului.
     */
    public static function numberField(): Select
    {
        return Select::make('number')
            ->label(__('panel.forms.term.number'))
            ->options(static fn (Get $get, ?Model $record): array => self::numberOptions($get, $record))
            ->required()
            ->native(false)
            ->live()
            ->afterStateUpdated(static function (mixed $state, Get $get, Set $set, ?Model $record): void {
                if (! is_numeric($state)) {
                    return;
                }

                // Denumirea urmează numărul cât timp e cea canonică (sau goală) — o denumire
                // editată justificat nu e suprascrisă.
                $currentName = $get('name');
                $canonicalForPrevious = $record instanceof Term
                    ? Term::canonicalName((int) $record->getOriginal('number'))
                    : null;

                if (blank($currentName)
                    || $currentName === $canonicalForPrevious
                    || in_array($currentName, array_filter(array_map(fn (int $n): ?string => Term::canonicalName($n), [1, 2, 3, 4])), true)) {
                    $set('name', Term::canonicalName((int) $state));
                }

                // Începutul propus: prima zi liberă (după ultimul semestru definit al anului,
                // altfel începutul anului) — doar când data e încă goală.
                if (blank($get('starts_on'))) {
                    $suggested = self::suggestedStart($get);

                    if ($suggested !== null) {
                        $set('starts_on', $suggested->toDateString());
                    }
                }
            })
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
            ]);
    }

    /**
     * Denumirea: completată AUTOMAT din număr, editabilă doar când e justificat (sesiune specială
     * etc.) — helper-ul o spune explicit.
     */
    public static function nameField(): TextInput
    {
        return TextInput::make('name')
            ->label(__('panel.forms.term.name'))
            ->required()
            ->maxLength(255)
            ->helperText(__('panel.forms.term.name_autofill_hint'));
    }

    /**
     * INFO BOX când anul ales are DEJA ambele semestre (lista de numere e goală): explică de ce
     * nu mai e nimic de creat — anul școlar are exact două semestre.
     */
    public static function yearFullInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->columnSpanFull()
            ->visibleOn('create')
            ->visible(static fn (Get $get): bool => is_numeric($get('academic_year_id'))
                && count(array_diff([1, 2], self::takenNumbers($get, null))) === 0)
            ->schema([
                Text::make(fn (): string => (string) __('panel.forms.term.year_terms_full'))->weight('bold'),
            ]);
    }

    /** Începutul: limitat la perioada anului chiar în calendar; propus automat la alegerea numărului. */
    public static function startsOnField(): DatePicker
    {
        return DatePicker::make('starts_on')
            ->label(__('panel.fields.starts_on'))
            ->required()
            ->minDate(fn (Get $get): ?Carbon => self::yearBound($get, 'start'))
            ->maxDate(fn (Get $get): ?Carbon => self::yearBound($get, 'end'));
    }

    /**
     * Sfârșitul: limitat la perioada anului; nu poate preceda începutul; intervalul trebuie să
     * încapă în an și să nu se suprapună cu alt semestru (Term::forDate ar deveni ambiguu).
     */
    public static function endsOnField(): DatePicker
    {
        return DatePicker::make('ends_on')
            ->label(__('panel.fields.ends_on'))
            ->required()
            ->afterOrEqual('starts_on')
            ->minDate(fn (Get $get): ?Carbon => self::yearBound($get, 'start'))
            ->maxDate(fn (Get $get): ?Carbon => self::yearBound($get, 'end'))
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

                    // Suprapunerea se verifică peste TOATE semestrele, indiferent de an: anii sunt
                    // secvențiali, iar derivarea semestrului din data notei/absenței devine ambiguă
                    // la ORICE suprapunere.
                    $overlaps = Term::query()
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->whereDate('starts_on', '<=', $value)
                        ->whereDate('ends_on', '>=', $startsOn)
                        ->exists();

                    if ($overlaps) {
                        $fail(__('panel.validation.term.overlap'));
                    }
                },
            ]);
    }

    /**
     * INFO BOX la editare: mutarea granițelor realiniază automat evaluările existente (TermObserver)
     * — administratorul află înainte, nu descoperă după.
     */
    public static function realignInfoBox(): Section
    {
        return Section::make()
            ->compact()
            ->secondary()
            ->columnSpanFull()
            ->visibleOn('edit')
            ->schema([
                Text::make(fn (): string => (string) __('panel.forms.term.realign_hint')),
            ]);
    }

    /**
     * Numerele DISPONIBILE în anul ales — anul are exact DOUĂ semestre (structura reală a
     * școlii), deci opțiunile sunt 1–2 minus cele deja definite. La editare, numărul propriu
     * rămâne selectabil. Când ambele există, lista e goală — info box-ul de alături explică.
     *
     * @return array<int, string>
     */
    private static function numberOptions(Get $get, ?Model $record): array
    {
        $taken = self::takenNumbers($get, $record);

        $options = [];

        foreach ([1, 2] as $number) {
            if (! in_array($number, $taken, true)) {
                $options[$number] = Term::canonicalName($number) ?? (string) $number;
            }
        }

        return $options;
    }

    /**
     * Numerele deja definite în anul ales (fără recordul editat).
     *
     * @return array<int, int>
     */
    private static function takenNumbers(Get $get, ?Model $record): array
    {
        $yearId = $get('academic_year_id') ?? ($record?->getAttribute('academic_year_id'));

        if (! is_numeric($yearId)) {
            return [];
        }

        return Term::query()
            ->where('academic_year_id', (int) $yearId)
            ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
            ->pluck('number')
            ->map(fn ($number): int => (int) $number)
            ->values()
            ->all();
    }

    /** Prima zi LIBERĂ a anului ales: după ultimul semestru definit, altfel începutul anului. */
    private static function suggestedStart(Get $get): ?Carbon
    {
        $year = self::resolvedYear($get);

        if ($year === null) {
            return null;
        }

        $lastEnd = Term::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('ends_on')
            ->max('ends_on');

        if ($lastEnd !== null) {
            return Carbon::parse($lastEnd)->addDay();
        }

        [$spanStart] = SchoolCalendar::yearSpan($year);

        return Carbon::parse($spanStart);
    }

    /** Granița anului ales (minDate/maxDate) — null când anul nu are interval definit. */
    private static function yearBound(Get $get, string $edge): ?Carbon
    {
        $year = self::resolvedYear($get);

        if ($year === null || $year->starts_on === null || $year->ends_on === null) {
            return null;
        }

        [$spanStart, $spanEnd] = SchoolCalendar::yearSpan($year);

        return $edge === 'start' ? Carbon::parse($spanStart) : Carbon::parse($spanEnd);
    }

    private static function resolvedYear(Get $get): ?AcademicYear
    {
        $yearId = $get('academic_year_id');

        return is_numeric($yearId) ? AcademicYear::query()->find((int) $yearId) : null;
    }
}
