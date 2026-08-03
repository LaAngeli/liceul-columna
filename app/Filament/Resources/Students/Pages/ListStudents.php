<?php

namespace App\Filament\Resources\Students\Pages;

use App\Enums\SchoolCycle;
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
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
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
        ];
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
