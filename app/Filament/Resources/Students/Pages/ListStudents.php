<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Enrollments\EnrollStudents;
use App\Enums\SchoolCycle;
use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Support\ClassRoster;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Pagina „Elevi" folosește navigatorul drill-down ADAPTAT: elevul nu are o clasă-coloană, ci
 * ÎNMATRICULĂRI (pe ani) — deci singura dimensiune e „Clase" (cardurile claselor → elevii lor),
 * iar constrângerea trece prin enrollments. Administrația păstrează accesul la TOT registrul
 * (elevi plecați / fără clasă curentă) prin vederea explicită „Arhivă".
 */
class ListStudents extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator {
        hasCatalogContext as baseHasCatalogContext;
        applyCatalogContext as baseApplyCatalogContext;
        catalogContextTitle as baseCatalogContextTitle;
        catalogContextSubtitle as baseCatalogContextSubtitle;
        catalogSiblingOptions as baseCatalogSiblingOptions;
        leaveCatalogContext as baseLeaveCatalogContext;
    }

    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    /** Vederea „toți elevii" (arhiva) — doar administrația; flag explicit în URL. */
    #[Url(as: 'arhiva', except: null)]
    public ?string $archiveMode = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('panel.catalog_nav.students_archive'))
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn (): bool => (auth('web')->user()?->isAdministrator() ?? false) && ! $this->isArchiveMode())
                ->action(function (): void {
                    $this->archiveMode = '1';
                    $this->catalogNavMemo = [];
                    $this->resetCatalogPagination();
                }),
            // ONBOARDING UNIFICAT: elevul NOU nu se mai creează ca fișă separată — butonul duce
            // în fluxul de cont (Utilizatori → creare, rol pre-completat), unde fișa, contul,
            // înmatricularea și părinții se leagă împreună. Numele „create" rămâne (testele de
            // autorizare pe listă verifică vizibilitatea lui); doar cine creează conturi îl vede.
            Action::make('create')
                ->label(__('panel.users_nav.onboard_student'))
                ->icon('heroicon-o-plus')
                ->url(UserResource::getUrl('create', ['rol' => UserRole::Elev->value]))
                ->visible(fn (): bool => auth('web')->user()?->canManageAccounts() ?? false),
            $this->addFicheOnlyAction(),
        ];
    }

    /**
     * „Adaugă elev fără cont": fișa + înmatricularea, FĂRĂ cont de acces (cerința beneficiarului,
     * 2026-08-03). La clasele mici contul elevului nu se folosește — familia intră pe contul
     * părintelui — iar fluxul unificat obliga la nașterea unui cont care rămânea nefolosit; în
     * plus, fiecare cont de minor în plus e o suprafață de date personale fără rost (L133).
     *
     * Elevul iese COMPLET funcțional în catalog: înmatricularea trece prin aceeași acțiune ca
     * registrul ({@see EnrollStudents}), iar contul se poate adăuga oricând din fișă. La final,
     * notificarea deschide drumul firesc mai departe — crearea contului de PĂRINTE, cu copilul
     * deja pre-selectat.
     */
    private function addFicheOnlyAction(): Action
    {
        return Action::make('createFicheOnly')
            ->label(__('panel.forms.student.fiche_only.label'))
            ->icon('heroicon-o-identification')
            ->color('gray')
            ->modalHeading(__('panel.forms.student.fiche_only.heading'))
            ->modalDescription(__('panel.forms.student.fiche_only.description'))
            ->modalSubmitActionLabel(__('panel.forms.student.fiche_only.submit'))
            ->visible(fn (): bool => (auth('web')->user()?->canConfigureSchool() ?? false)
                && ClassRoster::enrollmentYearId() !== null)
            ->schema([
                TextInput::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->required()
                    ->maxLength(120),
                Select::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->options(Sex::class)
                    ->native(false)
                    ->required(),
                Select::make('school_class_id')
                    ->label(__('panel.forms.user.enroll_class'))
                    ->helperText(__('panel.forms.user.enroll_class_hint'))
                    ->options(fn (): array => self::enrollmentClassOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        if (! is_numeric($state)) {
                            return;
                        }

                        if (blank($get('register_number'))) {
                            $set('register_number', ClassRoster::nextRegisterNumber((int) $state));
                        }

                        if (blank($get('english_group'))) {
                            $set('english_group', ClassRoster::suggestEnglishGroup((int) $state));
                        }
                    }),
                TextInput::make('register_number')
                    ->label(__('panel.fields.register_number'))
                    ->maxLength(10)
                    ->helperText(fn (Get $get): string => blank($get('school_class_id'))
                        ? (string) __('panel.forms.user.register_number_hint')
                        : (string) __('panel.forms.user.register_number_in_class_hint', [
                            'used' => ClassRoster::usedRegisterNumbers((int) $get('school_class_id')) === []
                                ? (string) __('panel.forms.user.register_number_none_used')
                                : implode(', ', ClassRoster::usedRegisterNumbers((int) $get('school_class_id'))),
                            'next' => ClassRoster::nextRegisterNumber((int) $get('school_class_id')),
                        ]))
                    ->rules([
                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $number = trim((string) $value);
                            $classId = $get('school_class_id');

                            if ($number === '' || blank($classId)) {
                                return;
                            }

                            if (ClassRoster::registerNumberTaken((int) $classId, $number)) {
                                $fail(__('panel.validation.student.register_number_in_class'));
                            }
                        },
                    ]),
                Select::make('second_language')
                    ->label(__('panel.forms.student.second_language'))
                    ->options(SecondLanguage::class)
                    ->default(SecondLanguage::None->value)
                    ->native(false)
                    ->required(),
                Select::make('english_group')
                    ->label(__('panel.forms.student.english_group_long'))
                    ->helperText(__('panel.forms.user.english_group_hint'))
                    ->options([
                        1 => __('panel.forms.student.group_option', ['group' => 1]),
                        2 => __('panel.forms.student.group_option', ['group' => 2]),
                    ])
                    ->native(false)
                    ->visible(fn (Get $get): bool => filled($get('school_class_id'))
                        && ClassRoster::usesEnglishGroups((int) $get('school_class_id')))
                    ->required(fn (Get $get): bool => filled($get('school_class_id'))
                        && ClassRoster::usesEnglishGroups((int) $get('school_class_id'))),
            ])
            ->action(function (array $data): void {
                $class = SchoolClass::query()->find((int) ($data['school_class_id'] ?? 0));

                if ($class === null || ! (auth('web')->user()?->canConfigureSchool() ?? false)) {
                    return;
                }

                $student = Student::query()->create([
                    'last_name' => trim((string) $data['last_name']),
                    'first_name' => trim((string) $data['first_name']),
                    'sex' => $data['sex'],
                    'register_number' => filled($data['register_number'] ?? null) ? trim((string) $data['register_number']) : null,
                    'second_language' => $data['second_language'],
                    'english_group' => filled($data['english_group'] ?? null) ? (int) $data['english_group'] : null,
                ]);

                $result = app(EnrollStudents::class)->handle($class, [(int) $student->getKey()]);

                if ($result['enrolled'] === 0) {
                    $student->delete();

                    Notification::make()
                        ->warning()
                        ->title(__('panel.forms.student.fiche_only.enroll_failed'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('panel.forms.student.fiche_only.success', [
                        'student' => $student->full_name,
                        'class' => trim($class->name.' '.($class->section ?? '')),
                    ]))
                    ->body(__('panel.forms.student.fiche_only.next_step'))
                    ->actions([
                        Action::make('createGuardian')
                            ->label(__('panel.forms.student.fiche_only.create_guardian'))
                            ->url(UserResource::getUrl('create', [
                                'rol' => UserRole::Parinte->value,
                                'copil' => $student->getKey(),
                            ]))
                            ->button(),
                    ])
                    ->persistent()
                    ->send();

                $this->catalogNavMemo = [];
            });
    }

    /** @return array<int, string> */
    private static function enrollmentClassOptions(): array
    {
        $yearId = ClassRoster::enrollmentYearId();

        if ($yearId === null) {
            return [];
        }

        $options = [];

        $classes = SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        foreach ($classes as $class) {
            $options[(int) $class->getKey()] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    protected function catalogBaseQuery(): Builder
    {
        return StudentResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        return StudentResource::getEloquentQuery();
    }

    protected function catalogDateColumn(): string
    {
        // Nefolosit la elevi (cardurile de clasă se construiesc din înmatriculări, nu din agregate
        // pe dată) — cerut doar de contractul trait-ului.
        return 'created_at';
    }

    /**
     * Elevii se navighează DOAR pe clase (disciplina/profesorul/perioada nu au sens aici).
     *
     * @return array<int, string>
     */
    protected function catalogDimensionKeys(): array
    {
        return ['clase'];
    }

    public function catalogHint(): string
    {
        return (string) __('panel.catalog_nav.students_hint');
    }

    // ── Aterizare: o casetă de căutare care face DOUĂ lucruri ───────────────────────────────
    // Cerința beneficiarului (2026-08-03): cine caută UN elev nu trebuie să ghicească întâi clasa.
    // Aceeași casetă filtrează cardurile (clasă / diriginte) ȘI listează elevii găsiți, cu salt
    // direct în fișă. Căutarea se face pe interogarea SCOPED a resursei → profesorul găsește doar
    // elevii claselor lui, fără nicio gardă suplimentară aici.

    /** Câți elevi găsiți arătăm direct în meniu (restul se rafinează scriind mai mult). */
    private const SEARCH_HITS_LIMIT = 12;

    public function catalogSearchPlaceholder(): ?string
    {
        return (string) __('panel.catalog_nav.students_search_placeholder');
    }

    public function catalogSearchHitsLabel(): string
    {
        return (string) __('panel.catalog_nav.students_search_results');
    }

    /**
     * Cardurile claselor, grupate pe cicluri — 30+ de clase într-o grilă unică nu se citesc.
     *
     * @return array<int, array{label: string, cards: array<int, array<string, mixed>>}>|null
     */
    public function catalogCardGroups(): ?array
    {
        /** @var array<int, int> $levels treapta fiecărei clase, keyed pe id */
        $levels = $this->catalogMemo('classLevels', fn (): array => $this->navigatorClasses()
            ->mapWithKeys(fn (SchoolClass $class): array => [(int) $class->getKey() => (int) $class->grade_level])
            ->all());

        $groups = [];

        foreach ($this->catalogEntityCards() as $card) {
            $cycle = SchoolCycle::fromGradeLevel($levels[$card['id']] ?? SchoolCycle::MIN_GRADE_LEVEL);
            $groups[$cycle->value][] = $card;
        }

        $ordered = [];

        // Ordinea firească a școlii: primar → gimnaziu → liceu (nu ordinea de apariție).
        foreach (SchoolCycle::cases() as $cycle) {
            if (isset($groups[$cycle->value])) {
                $ordered[] = [
                    'label' => (string) __('panel.catalog_nav.cycles.'.$cycle->value),
                    'cards' => $groups[$cycle->value],
                ];
            }
        }

        return $ordered;
    }

    /**
     * Elevii găsiți după nume, prenume sau număr matricol — fiecare cuvânt tastat trebuie să
     * apară undeva (deci „Popescu Ion" și „Ion Popescu" duc la același elev).
     *
     * @return array<int, array{id: int, title: string, meta: string|null, url: string}>
     */
    public function catalogSearchHits(): array
    {
        $term = $this->catalogSearchTerm();

        // Sub 2 caractere lista ar fi zgomot pur (o literă = jumătate din școală).
        if (mb_strlen($term) < 2) {
            return [];
        }

        /** @var array<int, array{id: int, title: string, meta: string|null, url: string}> $hits */
        $hits = $this->catalogMemo('studentHits', function () use ($term): array {
            $students = StudentResource::getEloquentQuery()
                ->with('latestEnrollment.schoolClass')
                ->where(function (Builder $query) use ($term): void {
                    foreach (preg_split('/\s+/u', $term) ?: [] as $word) {
                        $like = '%'.$word.'%';

                        $query->where(fn (Builder $part) => $part
                            ->where('last_name', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('register_number', 'like', $like));
                    }
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(self::SEARCH_HITS_LIMIT)
                ->get();

            $hits = [];

            foreach ($students as $student) {
                /** @var Student $student */
                $hits[] = [
                    'id' => (int) $student->getKey(),
                    'title' => $student->full_name,
                    'meta' => $this->studentHitMeta($student),
                    'url' => StudentResource::getUrl('view', ['record' => $student->getKey()]),
                ];
            }

            return $hits;
        });

        return $hits;
    }

    /** Linia a doua a unui rezultat: clasa (dacă există) + numărul matricol. */
    private function studentHitMeta(Student $student): ?string
    {
        $class = $student->latestEnrollment?->schoolClass;

        $parts = array_filter([
            $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
            $student->register_number !== null
                ? (string) __('panel.fields.register_number').': '.$student->register_number
                : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Clasa elevului = înmatricularea lui — constrângerea trece prin enrollments.
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToClass(Builder $query, ?SchoolClass $class): void
    {
        if ($class !== null) {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('school_class_id', $class->getKey()));
        }
    }

    /** Fără dimensiunile disciplină / profesor / semestru la elevi — parametrii din URL se ignoră. */
    protected function resolvedSubject(): ?Subject
    {
        return null;
    }

    protected function resolvedTeacher(): ?Teacher
    {
        return null;
    }

    protected function resolvedTerm(): ?Term
    {
        return null;
    }

    /**
     * Cardurile claselor: elevii înmatriculați + dirigintele + badge „Clasa mea" — fără agregate
     * pe dată (un elev nu are „ultima înregistrare" relevantă aici).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}>
     */
    protected function classCards(): array
    {
        $enrollments = $this->enrollmentCounts();

        $cards = [];

        foreach ($this->navigatorClasses() as $class) {
            if (! $this->classMatchesSearch($class)) {
                continue;
            }

            $students = $enrollments->get($class->id);
            $count = $students !== null ? (int) $students->aggregate : 0;

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'badge' => $this->isOwnHomeroomClass((int) $class->id)
                    ? (string) __('panel.catalog_nav.homeroom')
                    : null,
                'stats' => [
                    (string) trans_choice('panel.catalog_nav.students', $count, ['count' => $count]),
                ],
            ];
        }

        return $cards;
    }

    /** Cardul rămâne vizibil dacă termenul apare în numele clasei sau al dirigintelui. */
    private function classMatchesSearch(SchoolClass $class): bool
    {
        $term = $this->catalogSearchTerm();

        if ($term === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            trim($class->name.' '.($class->section ?? '')),
            $class->homeroomTeacher?->full_name,
        ])));

        return str_contains($haystack, mb_strtolower($term));
    }

    /**
     * Fără chips în contextul unei clase de elevi (nu există sub-dimensiune utilă).
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function catalogChips(): array
    {
        return [];
    }

    // ── Vederea „Arhivă" (toți elevii) — doar administrația ────────────────────────────────

    /** Public: tabelul citește vederea ca să arate coloana „Clasa" doar în arhivă. */
    public function isArchiveMode(): bool
    {
        return $this->archiveMode === '1' && (auth('web')->user()?->isAdministrator() ?? false);
    }

    public function hasCatalogContext(): bool
    {
        return $this->isArchiveMode() || $this->baseHasCatalogContext();
    }

    public function applyCatalogContext(Builder $query): Builder
    {
        // Arhiva = registrul complet, nescoped suplimentar (interogarea resursei rămâne sursa).
        return $this->isArchiveMode() ? $query : $this->baseApplyCatalogContext($query);
    }

    public function catalogContextTitle(): string
    {
        return $this->isArchiveMode()
            ? (string) __('panel.catalog_nav.students_archive')
            : $this->baseCatalogContextTitle();
    }

    public function catalogContextSubtitle(): ?string
    {
        return $this->isArchiveMode() ? null : $this->baseCatalogContextSubtitle();
    }

    /** @return array<int|string, string> */
    public function catalogSiblingOptions(): array
    {
        return $this->isArchiveMode() ? [] : $this->baseCatalogSiblingOptions();
    }

    public function leaveCatalogContext(): void
    {
        $this->archiveMode = null;

        $this->baseLeaveCatalogContext();
    }
}
