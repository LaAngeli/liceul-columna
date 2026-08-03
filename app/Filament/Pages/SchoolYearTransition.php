<?php

namespace App\Filament\Pages;

use App\Actions\AcademicYears\StartSchoolYear;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\HtmlString;

/**
 * „Trecerea în anul nou" — ecranul UNIC al operațiunii care închide un an și îl deschide pe
 * următorul (cerința beneficiarului, 2026-08-03: „sunt o mulțime de pași… se pot omite").
 *
 * Pașii existau, dar în patru secțiuni diferite (Ani școlari → Semestre → Ani școlari →
 * Înmatriculări) și cu o ordine care conta. Aici sunt o singură fereastră, în ordinea firească,
 * cu o previzualizare care spune EXACT ce se va întâmpla înainte de a se întâmpla — și cu ce
 * rămâne de făcut manual după (clasele I, orarul), ca lista să nu se termine cu o iluzie.
 *
 * Toată munca o fac Actions-urile existente, compuse de {@see StartSchoolYear}: pagina nu conține
 * nicio regulă proprie de business. Operațiunile individuale rămân disponibile pe cardurile
 * anului, pentru reluări parțiale.
 *
 * @property-read Schema $form
 */
class SchoolYearTransition extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'trecerea-in-anul-nou';

    protected string $view = 'filament.pages.school-year-transition';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Raportul ultimei execuții — rămâne pe ecran, ca operatorul să vadă ce s-a făcut. */
    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.year_transition.title');
    }

    public function getTitle(): string
    {
        return __('panel.pages.year_transition.title');
    }

    /** Operațiune de configurare a școlii: super-admin / director / administrator operațional. */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->defaults());
    }

    /**
     * Valorile de pornire: anul-sursă = anul în curs, numele anului nou dedus din el, semestrele
     * propuse după tiparul anului precedent. Operatorul confirmă, nu tastează de la zero.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $action = app(StartSchoolYear::class);
        $source = $this->currentYear();
        $startsOn = $source?->starts_on?->copy()->addYear()->toDateString();

        return [
            'source_year_id' => $source?->getKey(),
            'target_year_id' => null,
            'year' => [
                'name' => $action->suggestName($source),
                'starts_on' => $startsOn,
                'ends_on' => $source?->ends_on?->copy()->addYear()->toDateString(),
            ],
            'terms' => $action->suggestTerms($source, $startsOn),
            'with_assignments' => true,
            'graduate' => true,
            'promote' => true,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.pages.year_transition.step_source'))
                    ->description(__('panel.pages.year_transition.step_source_hint'))
                    ->schema([
                        Select::make('source_year_id')
                            ->label(__('panel.pages.year_transition.source_year'))
                            ->options(fn (): array => $this->yearOptions())
                            ->native(false)
                            ->required()
                            ->live(),
                    ]),

                Section::make(__('panel.pages.year_transition.step_year'))
                    ->description(__('panel.pages.year_transition.step_year_hint'))
                    ->columns(3)
                    ->schema([
                        // Anul poate exista deja (creat separat): atunci nu se mai naște altul.
                        Select::make('target_year_id')
                            ->label(__('panel.pages.year_transition.existing_year'))
                            ->helperText(__('panel.pages.year_transition.existing_year_hint'))
                            ->options(fn (Get $get): array => $this->targetYearOptions($get))
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('year.name')
                            ->label(__('panel.pages.year_transition.year_name'))
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => blank($get('target_year_id')))
                            ->required(fn (Get $get): bool => blank($get('target_year_id'))),
                        DatePicker::make('year.starts_on')
                            ->label(__('panel.fields.starts_on'))
                            ->native(false)
                            ->visible(fn (Get $get): bool => blank($get('target_year_id')))
                            ->required(fn (Get $get): bool => blank($get('target_year_id'))),
                        DatePicker::make('year.ends_on')
                            ->label(__('panel.fields.ends_on'))
                            ->native(false)
                            ->visible(fn (Get $get): bool => blank($get('target_year_id')))
                            ->required(fn (Get $get): bool => blank($get('target_year_id'))),
                    ]),

                Section::make(__('panel.pages.year_transition.step_terms'))
                    ->description(__('panel.pages.year_transition.step_terms_hint'))
                    ->schema([
                        Repeater::make('terms')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('panel.pages.year_transition.term_name'))
                                    ->required()
                                    ->maxLength(60),
                                DatePicker::make('starts_on')
                                    ->label(__('panel.fields.starts_on'))
                                    ->native(false)
                                    ->required(),
                                DatePicker::make('ends_on')
                                    ->label(__('panel.fields.ends_on'))
                                    ->native(false)
                                    ->required()
                                    ->afterOrEqual('starts_on'),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('panel.pages.year_transition.add_term'))
                            ->live()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),

                Section::make(__('panel.pages.year_transition.step_transfer'))
                    ->description(__('panel.pages.year_transition.step_transfer_hint'))
                    ->schema([
                        Toggle::make('with_assignments')
                            ->label(__('panel.actions.open_year.with_assignments'))
                            ->helperText(__('panel.actions.open_year.with_assignments_hint'))
                            ->live(),
                        Toggle::make('graduate')
                            ->label(__('panel.pages.year_transition.graduate'))
                            ->helperText(__('panel.pages.year_transition.graduate_hint'))
                            ->live(),
                        Toggle::make('promote')
                            ->label(__('panel.pages.year_transition.promote'))
                            ->helperText(__('panel.pages.year_transition.promote_hint'))
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Previzualizarea: ce se va întâmpla, cifră cu cifră, din aceeași sursă ca execuția.
     *
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    public function previewRows(): array
    {
        $state = $this->rawState();
        $plan = app(StartSchoolYear::class)->plan($this->inputFrom($state));

        if ($plan['blocked'] !== null) {
            return [[
                'label' => (string) __('panel.pages.year_transition.blocked'),
                'value' => (string) __('panel.actions.open_year.blocked.'.$plan['blocked']),
                'tone' => 'danger',
            ]];
        }

        $rows = [
            [
                'label' => (string) __('panel.pages.year_transition.preview_terms'),
                'value' => (string) $plan['terms'],
                'tone' => 'primary',
            ],
            [
                'label' => (string) __('panel.pages.year_transition.preview_classes'),
                'value' => (string) $plan['classes'],
                'tone' => 'primary',
            ],
        ];

        if (($state['with_assignments'] ?? true)) {
            $rows[] = [
                'label' => (string) __('panel.pages.year_transition.preview_assignments'),
                'value' => $plan['assignments'].($plan['dropped'] > 0
                    ? ' · '.__('panel.pages.year_transition.preview_dropped', ['count' => $plan['dropped']])
                    : ''),
                'tone' => 'primary',
            ];
        }

        if (($state['graduate'] ?? true)) {
            $rows[] = [
                'label' => (string) __('panel.pages.year_transition.preview_graduates'),
                'value' => $plan['graduates'].($plan['unmapped'] !== []
                    ? ' · '.implode(', ', $plan['unmapped'])
                    : ''),
                'tone' => 'success',
            ];
        }

        if (($state['promote'] ?? true)) {
            $rows[] = [
                'label' => (string) __('panel.pages.year_transition.preview_students'),
                'value' => (string) $plan['students'],
                'tone' => 'primary',
            ];
        }

        if ($plan['existing'] > 0) {
            $rows[] = [
                'label' => (string) __('panel.pages.year_transition.preview_existing'),
                'value' => (string) $plan['existing'],
                'tone' => 'warning',
            ];
        }

        return $rows;
    }

    /** Ce RĂMÂNE de făcut după — lista nu se termină cu o iluzie de „gata tot". */
    public function remainingSteps(): HtmlString
    {
        return new HtmlString(implode('<br>', array_map(
            fn (string $line): string => '• '.e($line),
            [
                (string) __('panel.pages.year_transition.remaining_first_grade'),
                (string) __('panel.pages.year_transition.remaining_repeaters'),
                (string) __('panel.pages.year_transition.remaining_timetable'),
            ],
        )));
    }

    public function start(): void
    {
        if (! ((auth('web')->user() instanceof User) && auth('web')->user()->canConfigureSchool())) {
            return;
        }

        $state = $this->form->getState();
        $result = app(StartSchoolYear::class)->handle($this->inputFrom($state));

        if ($result['blocked'] !== null) {
            Notification::make()
                ->warning()
                ->title(__('panel.actions.open_year.blocked.'.$result['blocked']))
                ->send();

            return;
        }

        $year = $result['year'];

        $this->report = [
            'year' => $year?->name,
            'terms' => $result['terms'],
            'classes' => $result['classes'],
            'assignments' => $result['assignments'],
            'graduates' => $result['graduates'],
            'students' => $result['students'],
            'existing' => $result['existing'],
        ];

        // Anul nou devine ținta implicită la o eventuală reluare (nu se mai creează altul).
        $this->form->fill([...$state, 'target_year_id' => $year?->getKey()]);

        Notification::make()
            ->success()
            ->title(__('panel.pages.year_transition.done', ['year' => $year === null ? '' : (string) $year->name]))
            ->body(__('panel.pages.year_transition.done_body'))
            ->send();
    }

    /**
     * Starea BRUTĂ a formularului, normalizată la array — `getRawState()` poate întoarce și un
     * Arrayable, iar previzualizarea o citește pe chei.
     *
     * @return array<string, mixed>
     */
    private function rawState(): array
    {
        $state = $this->form->getRawState();

        return $state instanceof Arrayable ? $state->toArray() : $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function inputFrom(array $state): array
    {
        return [
            'source_year_id' => $state['source_year_id'] ?? null,
            'target_year_id' => $state['target_year_id'] ?? null,
            'year' => $state['year'] ?? [],
            'terms' => array_values(array_filter($state['terms'] ?? [], is_array(...))),
            'with_assignments' => (bool) ($state['with_assignments'] ?? true),
            'graduate' => (bool) ($state['graduate'] ?? true),
            'promote' => (bool) ($state['promote'] ?? true),
        ];
    }

    /** Anul în curs: cel al semestrului curent, altfel cel mai recent cu clase. */
    private function currentYear(): ?AcademicYear
    {
        $id = Term::query()->where('is_current', true)->value('academic_year_id');

        if ($id !== null) {
            return AcademicYear::query()->find((int) $id);
        }

        return AcademicYear::query()
            ->whereHas('schoolClasses')
            ->orderByDesc('starts_on')
            ->first();
    }

    /** @return array<int, string> */
    private function yearOptions(): array
    {
        return AcademicYear::query()
            ->whereHas('schoolClasses')
            ->orderByDesc('starts_on')
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * Anii care pot fi ȚINTĂ: cei de DUPĂ sursă, deschiși. Restul ar fi o alegere fără sens.
     *
     * @return array<int, string>
     */
    private function targetYearOptions(Get $get): array
    {
        $source = blank($get('source_year_id')) ? null : AcademicYear::query()->find((int) $get('source_year_id'));

        return AcademicYear::query()
            ->whereNull('closed_at')
            ->when($source?->starts_on !== null, fn ($query) => $query->where('starts_on', '>', $source->starts_on))
            ->when($source !== null, fn ($query) => $query->whereKeyNot($source->getKey()))
            ->orderBy('starts_on')
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /** Câte clase are anul-sursă — folosit de ecran ca semnal „e ceva de transferat". */
    public function sourceClassCount(): int
    {
        $id = $this->rawState()['source_year_id'] ?? null;

        return blank($id) ? 0 : SchoolClass::query()->where('academic_year_id', (int) $id)->count();
    }
}
