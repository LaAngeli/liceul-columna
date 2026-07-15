<?php

namespace App\Filament\Concerns;

use App\Filament\Contracts\CatalogNavigator;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\ContentTranslator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Navigatorul catalogului: transformă pagina de listare dintr-un tabel plat într-un meniu
 * drill-down — alegi DIMENSIUNEA (clase / discipline / profesori / perioade), apoi ENTITATEA
 * (carduri cu statistici), apoi lucrezi în tabelul restrâns la context, cu sub-navigare (chips)
 * și comutator între entități-surori. Starea trăiește în URL (link-uri partajabile, back
 * funcțional).
 *
 * SECURITATE: id-urile din URL sunt doar „dorințe" — orice entitate se rezolvă prin seturile
 * permise rolului (validare la CITIRE, deci imună la manipularea proprietăților Livewire), iar
 * contextul doar îngustează interogarea deja scoped a resursei (nu poate lărgi accesul).
 *
 * Pagina care îl folosește implementează {@see CatalogNavigator} și
 * definește sursa de date prin cele trei metode abstracte de mai jos (Note azi, Absențe mâine).
 */
trait HasCatalogNavigator
{
    /** Dimensiunea activă a navigatorului (tab-ul de sus). */
    #[Url(as: 'vedere', except: 'clase')]
    public string $catalogDimension = 'clase';

    /** Entitatea primară / chip-ul secundar — id-uri „dorite" din URL, validate la citire. */
    #[Url(as: 'clasa', except: null)]
    public ?string $catalogClass = null;

    #[Url(as: 'disciplina', except: null)]
    public ?string $catalogSubject = null;

    #[Url(as: 'profesor', except: null)]
    public ?string $catalogTeacher = null;

    #[Url(as: 'perioada', except: null)]
    public ?string $catalogTerm = null;

    /** @var array<string, mixed> memoizare per-request (seturi permise, entități rezolvate, agregate) */
    protected array $catalogNavMemo = [];

    // ── Sursa de date (definite de pagina gazdă) ────────────────────────────────────────────

    /**
     * Interogarea de BAZĂ, deja scoped pe rol (de regulă `Resource::getEloquentQuery()`).
     *
     * @return Builder<Model>
     */
    abstract protected function catalogBaseQuery(): Builder;

    /**
     * Interogarea pentru NUMĂRĂTORI (scoped + doar rândurile care contează, ex. note ne-anulate).
     *
     * @return Builder<Model>
     */
    abstract protected function catalogCountableQuery(): Builder;

    /**
     * Coloana de dată a înregistrării (ex. `graded_on` / `occurred_on`).
     *
     * @return literal-string
     */
    abstract protected function catalogDateColumn(): string;

    // ── Dimensiuni ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, string> cheie dimensiune => etichetă tradusă */
    public function catalogDimensions(): array
    {
        $dimensions = [
            'clase' => (string) __('panel.catalog_nav.dimensions.clase'),
            'discipline' => (string) __('panel.catalog_nav.dimensions.discipline'),
        ];

        // Dimensiunea „Profesori" are sens doar pentru administrație (profesorul e propriul autor).
        if ($this->catalogUser()?->isAdministrator() ?? false) {
            $dimensions['profesori'] = (string) __('panel.catalog_nav.dimensions.profesori');
        }

        $dimensions['perioade'] = (string) __('panel.catalog_nav.dimensions.perioade');

        return $dimensions;
    }

    /** Dimensiunea activă, garantat validă pentru rolul curent. */
    public function catalogActiveDimension(): string
    {
        $available = array_keys($this->catalogDimensions());

        return in_array($this->catalogDimension, $available, true) ? $this->catalogDimension : 'clase';
    }

    // ── Acțiuni Livewire (meniul propriu-zis) ───────────────────────────────────────────────

    public function setCatalogDimension(string $dimension): void
    {
        $this->catalogDimension = array_key_exists($dimension, $this->catalogDimensions()) ? $dimension : 'clase';
        $this->catalogClass = null;
        $this->catalogSubject = null;
        $this->catalogTeacher = null;
        $this->catalogTerm = null;
        $this->catalogNavMemo = [];
        $this->resetCatalogPagination();
    }

    /** Deschide o entitate primară (card / comutatorul de surori) în dimensiunea activă. */
    public function openCatalogEntity(int|string $id): void
    {
        $id = (string) (int) $id;

        match ($this->catalogActiveDimension()) {
            'discipline' => [$this->catalogSubject = $id, $this->catalogClass = null],
            'profesori' => [$this->catalogTeacher = $id, $this->catalogClass = null],
            'perioade' => [$this->catalogTerm = $id, $this->catalogClass = null],
            default => [$this->catalogClass = $id, $this->catalogSubject = null],
        };

        $this->catalogNavMemo = [];
        $this->resetCatalogPagination();
    }

    /** Chip-ul de sub-navigare din context (null = „Toate"). */
    public function setCatalogChip(int|string|null $id): void
    {
        $id = $id === null || $id === '' ? null : (string) (int) $id;

        if ($this->catalogActiveDimension() === 'clase') {
            $this->catalogSubject = $id;
        } else {
            $this->catalogClass = $id;
        }

        $this->catalogNavMemo = [];
        $this->resetCatalogPagination();
    }

    /** Înapoi la cardurile dimensiunii curente. */
    public function leaveCatalogContext(): void
    {
        $this->catalogClass = null;
        $this->catalogSubject = null;
        $this->catalogTeacher = null;
        $this->catalogTerm = null;
        $this->catalogNavMemo = [];
        $this->resetCatalogPagination();
    }

    protected function resetCatalogPagination(): void
    {
        // Paginile de listare Filament au mereu paginare (Livewire WithPagination).
        $this->resetPage();
    }

    // ── Context (entitatea primară rezolvată + aplicarea pe interogare) ─────────────────────

    public function hasCatalogContext(): bool
    {
        return $this->catalogPrimaryModel() !== null;
    }

    /** Entitatea primară a contextului, validată pe scope (null = fără context). */
    public function catalogPrimaryModel(): ?Model
    {
        return match ($this->catalogActiveDimension()) {
            'discipline' => $this->resolvedSubject(),
            'profesori' => $this->resolvedTeacher(),
            'perioade' => $this->resolvedTerm(),
            default => $this->resolvedClass(),
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyCatalogContext(Builder $query): Builder
    {
        if (! $this->hasCatalogContext()) {
            return $query;
        }

        $dimension = $this->catalogActiveDimension();

        match ($dimension) {
            'discipline' => $query->where('subject_id', $this->resolvedSubject()?->getKey()),
            'profesori' => $query->where('teacher_id', $this->resolvedTeacher()?->getKey()),
            'perioade' => $query->where('term_id', $this->resolvedTerm()?->getKey()),
            default => $query->where('school_class_id', $this->resolvedClass()?->getKey()),
        };

        // Chip-ul secundar restrânge suplimentar: disciplina în interiorul clasei / clasa în rest.
        if ($dimension === 'clase') {
            if (($subject = $this->resolvedSubject()) !== null) {
                $query->where('subject_id', $subject->getKey());
            }
        } elseif (($class = $this->resolvedClass()) !== null) {
            $query->where('school_class_id', $class->getKey());
        }

        return $query;
    }

    public function catalogClassIdInContext(): ?int
    {
        $id = $this->resolvedClass()?->getKey();

        return $id === null ? null : (int) $id;
    }

    public function catalogSubjectIdInContext(): ?int
    {
        $id = $this->resolvedSubject()?->getKey();

        return $id === null ? null : (int) $id;
    }

    public function catalogTermIdInContext(): ?int
    {
        $id = $this->resolvedTerm()?->getKey();

        return $id === null ? null : (int) $id;
    }

    // ── Cardurile dimensiunii active ────────────────────────────────────────────────────────

    /**
     * @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}>
     */
    public function catalogEntityCards(): array
    {
        /** @var array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}> $cards */
        $cards = $this->catalogMemo('cards.'.$this->catalogActiveDimension(), fn (): array => match ($this->catalogActiveDimension()) {
            'discipline' => $this->subjectCards(),
            'profesori' => $this->teacherCards(),
            'perioade' => $this->termCards(),
            default => $this->classCards(),
        });

        return $cards;
    }

    /** @return array<int|string, string> opțiuni pentru comutatorul „sari la altă entitate" */
    public function catalogSiblingOptions(): array
    {
        $options = [];

        foreach ($this->catalogEntityCards() as $card) {
            $options[$card['id']] = $card['title'];
        }

        return $options;
    }

    /** @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}> */
    protected function classCards(): array
    {
        $aggregates = $this->aggregatesBy('school_class_id');
        $enrollments = $this->enrollmentCounts();

        $cards = [];

        foreach ($this->navigatorClasses() as $class) {
            $row = $aggregates->get($class->id);
            $students = $enrollments->get($class->id);

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'badge' => $this->isOwnHomeroomClass((int) $class->id)
                    ? (string) __('panel.catalog_nav.homeroom')
                    : null,
                'stats' => array_values(array_filter([
                    $students !== null
                        ? (string) trans_choice('panel.catalog_nav.students', (int) $students->aggregate, ['count' => (int) $students->aggregate])
                        : null,
                    $this->countStat($row),
                    $this->lastDateStat($row),
                ])),
            ];
        }

        return $cards;
    }

    /** @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}> */
    protected function subjectCards(): array
    {
        $aggregates = $this->aggregatesBy('subject_id', withClasses: true);

        $subjectIds = $aggregates->keys()
            ->merge($this->catalogTeacherModel()?->taughtSubjectIds() ?? [])
            ->unique()
            ->all();

        $cards = [];

        foreach (Subject::query()->whereKey($subjectIds)->get() as $subject) {
            $row = $aggregates->get($subject->id);

            $cards[] = [
                'id' => (int) $subject->id,
                'title' => ContentTranslator::subject($subject->name),
                'subtitle' => null,
                'badge' => null,
                'stats' => array_values(array_filter([
                    $row !== null && (int) $row->classes > 0
                        ? (string) trans_choice('panel.catalog_nav.classes', (int) $row->classes, ['count' => (int) $row->classes])
                        : null,
                    $this->countStat($row),
                    $this->lastDateStat($row),
                ])),
            ];
        }

        usort($cards, fn (array $a, array $b): int => strcoll($a['title'], $b['title']));

        return $cards;
    }

    /** @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}> */
    protected function teacherCards(): array
    {
        $aggregates = $this->aggregatesBy('teacher_id', withClasses: true, withSubjects: true);

        $cards = [];

        foreach (Teacher::query()->whereKey($aggregates->keys()->all())->orderBy('last_name')->orderBy('first_name')->get() as $teacher) {
            $row = $aggregates->get($teacher->id);

            $cards[] = [
                'id' => (int) $teacher->id,
                'title' => $teacher->full_name,
                'subtitle' => null,
                'badge' => null,
                'stats' => array_values(array_filter([
                    $row !== null && (int) $row->subjects > 0
                        ? (string) trans_choice('panel.catalog_nav.subjects', (int) $row->subjects, ['count' => (int) $row->subjects])
                        : null,
                    $row !== null && (int) $row->classes > 0
                        ? (string) trans_choice('panel.catalog_nav.classes', (int) $row->classes, ['count' => (int) $row->classes])
                        : null,
                    $this->countStat($row),
                    $this->lastDateStat($row),
                ])),
            ];
        }

        return $cards;
    }

    /** @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}> */
    protected function termCards(): array
    {
        $aggregates = $this->aggregatesBy('term_id');

        $cards = [];

        $terms = Term::query()
            ->with('academicYear')
            ->orderByDesc('starts_on')
            ->get();

        foreach ($terms as $term) {
            $row = $aggregates->get($term->id);

            $cards[] = [
                'id' => (int) $term->id,
                'title' => $term->name,
                'subtitle' => $term->academicYear?->name,
                'badge' => $term->is_current ? (string) __('panel.catalog_nav.current_term') : null,
                'stats' => array_values(array_filter([
                    $this->countStat($row),
                    $this->lastDateStat($row),
                ])),
            ];
        }

        return $cards;
    }

    // ── Bara de context + chips ─────────────────────────────────────────────────────────────

    public function catalogContextTitle(): string
    {
        $model = $this->catalogPrimaryModel();

        return match (true) {
            $model instanceof SchoolClass => trim($model->name.' '.($model->section ?? '')),
            $model instanceof Subject => ContentTranslator::subject($model->name),
            $model instanceof Teacher => $model->full_name,
            $model instanceof Term => $model->name.($model->academicYear !== null ? ' · '.$model->academicYear->name : ''),
            default => '',
        };
    }

    public function catalogContextSubtitle(): ?string
    {
        $model = $this->catalogPrimaryModel();

        if ($model instanceof SchoolClass) {
            return $model->homeroomTeacher?->full_name;
        }

        return null;
    }

    /**
     * Chips de sub-navigare în context: disciplinele clasei / clasele disciplinei etc.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function catalogChips(): array
    {
        /** @var array<int, array{id: int, label: string}> $chips */
        $chips = $this->catalogMemo('chips', function (): array {
            $model = $this->catalogPrimaryModel();

            return match (true) {
                $model instanceof SchoolClass => $this->classSubjectChips($model),
                $model instanceof Subject => $this->classChipsFor(fn (Builder $q) => $q->where('subject_id', $model->getKey()), $this->assignmentClassIds(subjectId: (int) $model->getKey())),
                $model instanceof Teacher => $this->classChipsFor(fn (Builder $q) => $q->where('teacher_id', $model->getKey()), $this->assignmentClassIds(teacherId: (int) $model->getKey())),
                $model instanceof Term => $this->classChipsFor(fn (Builder $q) => $q->where('term_id', $model->getKey())),
                default => [],
            };
        });

        return $chips;
    }

    /** Id-ul chip-ului activ (disciplina în dimensiunea „clase", clasa în rest). */
    public function catalogActiveChipId(): ?int
    {
        return $this->catalogActiveDimension() === 'clase'
            ? $this->catalogSubjectIdInContext()
            : $this->catalogClassIdInContext();
    }

    public function catalogChipsLabel(): string
    {
        return $this->catalogActiveDimension() === 'clase'
            ? (string) __('panel.catalog_nav.chips_subjects')
            : (string) __('panel.catalog_nav.chips_classes');
    }

    /**
     * Parametrii de context propagați către formularul de creare (clasă/disciplină pre-completate).
     *
     * @return array<string, int>
     */
    public function catalogCreateUrlParameters(): array
    {
        return array_filter([
            'clasa' => $this->catalogClassIdInContext(),
            'disciplina' => $this->catalogSubjectIdInContext(),
        ]);
    }

    // ── Interogări interne ──────────────────────────────────────────────────────────────────

    protected function catalogUser(): ?User
    {
        /** @var User|null */
        return auth('web')->user();
    }

    /** Profesorul din spatele contului — null pentru administrație (care vede nescoped). */
    protected function catalogTeacherModel(): ?Teacher
    {
        $user = $this->catalogUser();

        return ($user !== null && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    protected function isOwnHomeroomClass(int $classId): bool
    {
        return in_array($classId, $this->catalogTeacherModel()?->homeroomSchoolClassIds() ?? [], true);
    }

    /**
     * Agregatele interogării numărabile, grupate pe o coloană: total (`aggregate`) + ultima dată
     * (`last_on`) + opțional numărul de clase / discipline distincte (`classes` / `subjects`).
     *
     * @param  literal-string  $column
     * @return Collection<int|string, \stdClass>
     */
    protected function aggregatesBy(string $column, bool $withClasses = false, bool $withSubjects = false): Collection
    {
        $dateColumn = $this->catalogDateColumn();

        $select = [$column, 'COUNT(*) AS aggregate', "MAX({$dateColumn}) AS last_on"];

        if ($withClasses) {
            $select[] = 'COUNT(DISTINCT school_class_id) AS classes';
        }

        if ($withSubjects) {
            $select[] = 'COUNT(DISTINCT subject_id) AS subjects';
        }

        return $this->catalogCountableQuery()
            ->toBase()
            ->whereNotNull($column)
            ->selectRaw(implode(', ', $select))
            ->groupBy($column)
            ->get()
            ->keyBy($column);
    }

    /**
     * Clasele afișate drept carduri: profesorul — clasele lui; administrația — clasele anului
     * curent (anii trecuți rămân accesibili prin dimensiunea „Perioade").
     *
     * @return Collection<int, SchoolClass>
     */
    protected function navigatorClasses(): Collection
    {
        $query = SchoolClass::query()
            ->with('homeroomTeacher')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section');

        if (($teacher = $this->catalogTeacherModel()) !== null) {
            $query->whereKey($teacher->visibleSchoolClassIds());
        } elseif (($yearId = $this->currentAcademicYearId()) !== null) {
            $query->where('academic_year_id', $yearId);
        }

        return $query->get();
    }

    protected function currentAcademicYearId(): ?int
    {
        /** @var int|null $id */
        $id = $this->catalogMemo('currentYear', function (): ?int {
            $id = Term::query()->where('is_current', true)->value('academic_year_id');

            return $id === null ? null : (int) $id;
        });

        return $id;
    }

    /** @return Collection<int|string, \stdClass> nr. de elevi înmatriculați per clasă */
    protected function enrollmentCounts(): Collection
    {
        return Enrollment::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');
    }

    /** @return array<int, array{id: int, label: string}> disciplinele navigabile în interiorul unei clase */
    protected function classSubjectChips(SchoolClass $class): array
    {
        $teacher = $this->catalogTeacherModel();

        // Disciplinele predate în clasă (alocări): dirigintele + administrația le văd pe toate,
        // profesorul doar pe ale lui — oglinda exactă a scope-ului de citire al catalogului.
        $assignments = TeachingAssignment::query()
            ->where('school_class_id', $class->getKey())
            ->when($teacher !== null && ! $this->isOwnHomeroomClass((int) $class->getKey()),
                fn (Builder $q) => $q->where('teacher_id', $teacher->id))
            ->pluck('subject_id');

        // ∪ disciplinele care au deja înregistrări în clasă (acoperă datele istorice fără alocare).
        $graded = $this->catalogCountableQuery()
            ->toBase()
            ->where('school_class_id', $class->getKey())
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id');

        $chips = [];

        foreach (Subject::query()->whereKey($assignments->merge($graded)->unique()->all())->get() as $subject) {
            $chips[] = ['id' => (int) $subject->id, 'label' => ContentTranslator::subject($subject->name)];
        }

        usort($chips, fn (array $a, array $b): int => strcoll($a['label'], $b['label']));

        return $chips;
    }

    /**
     * Chips de CLASE pentru contextele disciplină / profesor / perioadă: clasele cu înregistrări
     * în context (∪ clasele din alocări, unde e cazul), mereu în interiorul scope-ului.
     *
     * @param  \Closure(Builder<Model>): Builder<Model>  $constraint
     * @param  array<int, int>  $extraClassIds
     * @return array<int, array{id: int, label: string}>
     */
    protected function classChipsFor(\Closure $constraint, array $extraClassIds = []): array
    {
        /** @var Builder<Model> $query */
        $query = $this->catalogCountableQuery();
        $constraint($query);

        $classIds = $query
            ->toBase()
            ->distinct()
            ->pluck('school_class_id')
            ->merge($extraClassIds)
            ->unique()
            ->intersect($this->allowedClassIds())
            ->all();

        $chips = [];

        $classes = SchoolClass::query()
            ->whereKey($classIds)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        foreach ($classes as $class) {
            $chips[] = ['id' => (int) $class->id, 'label' => trim($class->name.' '.($class->section ?? ''))];
        }

        return $chips;
    }

    /**
     * Clasele din alocări pentru o disciplină / un profesor (întregesc chips-urile cu clasele
     * fără înregistrări încă). Profesorul e limitat la propriile alocări.
     *
     * @return array<int, int>
     */
    protected function assignmentClassIds(?int $subjectId = null, ?int $teacherId = null): array
    {
        $teacher = $this->catalogTeacherModel();

        return TeachingAssignment::query()
            ->when($subjectId !== null, fn (Builder $q) => $q->where('subject_id', $subjectId))
            ->when($teacherId !== null, fn (Builder $q) => $q->where('teacher_id', $teacherId))
            ->when($teacher !== null, fn (Builder $q) => $q->where('teacher_id', $teacher->id))
            ->pluck('school_class_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    // ── Entități rezolvate + seturi permise (validarea la citire) ───────────────────────────

    protected function resolvedClass(): ?SchoolClass
    {
        /** @var SchoolClass|null */
        return $this->catalogMemo('class', function (): ?SchoolClass {
            $id = $this->catalogIntParam($this->catalogClass);

            return ($id !== null && in_array($id, $this->allowedClassIds(), true))
                ? SchoolClass::query()->with('homeroomTeacher')->find($id)
                : null;
        });
    }

    protected function resolvedSubject(): ?Subject
    {
        /** @var Subject|null */
        return $this->catalogMemo('subject', function (): ?Subject {
            $id = $this->catalogIntParam($this->catalogSubject);

            return ($id !== null && in_array($id, $this->allowedSubjectIds(), true))
                ? Subject::query()->find($id)
                : null;
        });
    }

    protected function resolvedTeacher(): ?Teacher
    {
        /** @var Teacher|null */
        return $this->catalogMemo('teacher', function (): ?Teacher {
            $id = $this->catalogIntParam($this->catalogTeacher);

            // Doar administrația navighează pe profesori.
            if ($id === null || ! ($this->catalogUser()?->isAdministrator() ?? false)) {
                return null;
            }

            return Teacher::query()->find($id);
        });
    }

    protected function resolvedTerm(): ?Term
    {
        /** @var Term|null */
        return $this->catalogMemo('term', function (): ?Term {
            $id = $this->catalogIntParam($this->catalogTerm);

            return $id !== null ? Term::query()->with('academicYear')->find($id) : null;
        });
    }

    /** @return array<int, int> */
    protected function allowedClassIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = $this->catalogMemo('allowedClasses', function (): array {
            if (($teacher = $this->catalogTeacherModel()) !== null) {
                return $teacher->visibleSchoolClassIds();
            }

            return SchoolClass::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        });

        return $ids;
    }

    /** @return array<int, int> */
    protected function allowedSubjectIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = $this->catalogMemo('allowedSubjects', function (): array {
            $teacher = $this->catalogTeacherModel();

            if ($teacher === null) {
                return Subject::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            }

            // Predatele lui ∪ disciplinele vizibile prin diriginție (au înregistrări în scope).
            $graded = $this->catalogCountableQuery()
                ->toBase()
                ->whereNotNull('subject_id')
                ->distinct()
                ->pluck('subject_id')
                ->map(fn ($id): int => (int) $id);

            return $graded->merge($teacher->taughtSubjectIds())->unique()->values()->all();
        });

        return $ids;
    }

    protected function catalogIntParam(?string $raw): ?int
    {
        if ($raw === null || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @template TValue
     *
     * @param  \Closure(): TValue  $resolver
     * @return TValue
     */
    protected function catalogMemo(string $key, \Closure $resolver): mixed
    {
        if (! array_key_exists($key, $this->catalogNavMemo)) {
            $this->catalogNavMemo[$key] = $resolver();
        }

        return $this->catalogNavMemo[$key];
    }

    // ── Formatare statistici carduri ────────────────────────────────────────────────────────

    protected function countStat(?\stdClass $row): string
    {
        $count = $row !== null ? (int) $row->aggregate : 0;

        return $count > 0
            ? (string) trans_choice('panel.catalog_nav.records', $count, ['count' => $count])
            : (string) __('panel.catalog_nav.no_records');
    }

    protected function lastDateStat(?\stdClass $row): ?string
    {
        if ($row === null || $row->last_on === null) {
            return null;
        }

        return (string) __('panel.catalog_nav.last_record', [
            'date' => Carbon::parse($row->last_on)->translatedFormat('d.m.Y'),
        ]);
    }
}
