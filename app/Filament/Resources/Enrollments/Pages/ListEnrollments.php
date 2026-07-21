<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Înmatriculări" = REGISTRUL claselor (2026-07-16, cerința beneficiarului: același
 * principiu de navigare + logică reprezentativă scopului). Lista plată de înmatriculări nu mai
 * e interfața: pastile pe ani școlari → cardurile claselor (diriginte + elevi activi / plecați)
 * → registrul clasei (tabelul înmatriculărilor ei), cu adăugare PRE-COMPLETATĂ pe clasă și
 * marcarea plecării direct din rând.
 *
 * Secțiunea e a administrației (ConfiguresSchool: citire pentru administrația academică,
 * scriere doar pentru configuratori) — nu are nevoie de scoping pe rol la carduri.
 */
class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected string $view = 'filament.catalog.enrollments-navigator';

    /** Anul școlar activ (id „dorit" din URL, validat la citire prin anii cu clase). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** Clasa al cărei registru e deschis (validată la citire). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** Lista elevilor NEînmatriculați în anul activ, deschisă/închisă din cardul dedicat. */
    public bool $showUnassigned = false;

    /** @var Collection<int|string, int>|null nr. de clase per an școlar (memoizat pe instanță) */
    private ?Collection $classCountsByYear = null;

    private SchoolClass|false|null $activeClassMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Din registrul unei clase, adăugarea vine pre-completată (an + clasă) —
                // formularul își validează singur contextul (id străin = ignorat).
                ->url(function (): string {
                    $class = $this->activeClass();

                    return EnrollmentResource::getUrl('create', $class !== null
                        ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey()]
                        : []);
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openYear(int|string $id): void
    {
        $id = (int) $id;

        if ($this->classCountsByYear()->has($id)) {
            $this->yearParam = (string) $id;
        }
    }

    public function openClass(int|string $id): void
    {
        $id = (int) $id;

        if (SchoolClass::query()->whereKey($id)->exists()) {
            $this->classParam = (string) $id;
            $this->activeClassMemo = null;
        }
    }

    public function leaveClass(): void
    {
        $this->classParam = null;
        $this->activeClassMemo = null;
    }

    public function activeClass(): ?SchoolClass
    {
        if ($this->activeClassMemo === null) {
            $this->activeClassMemo = ($this->classParam !== null && ctype_digit($this->classParam))
                ? (SchoolClass::query()->with(['homeroomTeacher', 'academicYear'])->whereKey((int) $this->classParam)->first() ?? false)
                : false;
        }

        return $this->activeClassMemo === false ? null : $this->activeClassMemo;
    }

    /** Anul activ: cel cerut prin URL dacă are clase, altfel anul CURENT, altfel cel mai recent. */
    public function activeYearId(): ?int
    {
        $visible = $this->classCountsByYear();

        if ($this->yearParam !== null && ctype_digit($this->yearParam) && $visible->has((int) $this->yearParam)) {
            return (int) $this->yearParam;
        }

        $currentYearId = Term::query()->where('is_current', true)->value('academic_year_id');

        if ($currentYearId !== null && $visible->has((int) $currentYearId)) {
            return (int) $currentYearId;
        }

        $newest = $visible->keys()->sortDesc()->first();

        return $newest !== null ? (int) $newest : null;
    }

    /**
     * Registrul clasei active — constrângerea tabelului (apelată din EnrollmentsTable).
     *
     * @param  Builder<Enrollment>  $query
     * @return Builder<Enrollment>
     */
    public function applyRosterContext(Builder $query): Builder
    {
        $class = $this->activeClass();

        return $class !== null
            ? $query->where('school_class_id', $class->getKey())
            : $query;
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Pastilele anilor (cu clase), badge = numărul de înmatriculări din registrul anului —
     * un an nou fără înmatriculări apare cu 0 (acolo urmează să se înmatriculeze).
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $classCounts = $this->classCountsByYear();

        if ($classCounts->isEmpty()) {
            return [];
        }

        $enrollmentCounts = Enrollment::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id');

        return AcademicYear::query()
            ->whereKey($classCounts->keys()->all())
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => (int) $year->id,
                'label' => (string) $year->name,
                'count' => (int) ($enrollmentCounts->get($year->id) ?? 0),
            ])
            ->all();
    }

    /**
     * Cardurile claselor anului activ: diriginte + elevi ACTIVI (fără plecare) și PLECAȚI —
     * registrul pe scurt, nu doar un total.
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>}>
     */
    public function classCards(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $classes = SchoolClass::query()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $yearId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $rosterCounts = Enrollment::query()
            ->toBase()
            ->selectRaw('school_class_id, SUM(CASE WHEN left_on IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN left_on IS NOT NULL THEN 1 ELSE 0 END) AS departed')
            ->whereIn('school_class_id', $classes->pluck('id')->all())
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');

        $cards = [];

        foreach ($classes as $class) {
            $counts = $rosterCounts->get($class->id);
            $active = (int) ($counts->active ?? 0);
            $departed = (int) ($counts->departed ?? 0);

            $stats = [];

            if ($active > 0 || $departed > 0) {
                $stats[] = (string) trans_choice('panel.catalog_nav.active_students', $active, ['count' => $active]);

                if ($departed > 0) {
                    $stats[] = (string) trans_choice('panel.catalog_nav.departed_students', $departed, ['count' => $departed]);
                }
            } else {
                $stats[] = (string) __('panel.catalog_nav.no_enrollments');
            }

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                // Clasa fără diriginte = coadă de validare fără validator (motivări) și registru
                // fără responsabil — chip de avertisment direct pe card, nu doar în widget.
                'no_homeroom' => $class->homeroomTeacher === null,
                'stats' => $stats,
            ];
        }

        return $cards;
    }

    public function enrollmentsHint(): string
    {
        return (string) __('panel.catalog_nav.enrollments_hint');
    }

    public function toggleUnassigned(): void
    {
        $this->showUnassigned = ! $this->showUnassigned;
    }

    // ── Semnale de integritate + neînmatriculații ───────────────────────────────────────────

    /**
     * Elevii ACTIVI fără NICIO înmatriculare (nici măcar arhivată) în anul activ — lista de
     * lucru la deschiderea anului și plasa pentru omisiuni. Cei cu înmatriculare ARHIVATĂ nu
     * apar aici (formularul îi refuză cu îndrumare spre restaurare — semnal separat).
     *
     * @return array{count: int, students: list<array{id: int, name: string, register: string|null, enroll_url: string}>}
     */
    public function unassigned(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return ['count' => 0, 'students' => []];
        }

        $query = Student::query()
            // Fără scope-ul SoftDeletes pe subinterogare (echivalent withTrashed): un rând ARHIVAT
            // tot îl scoate pe elev din „de înmatriculat" — formularul l-ar refuza cu îndrumare
            // spre restaurare (archived_duplicate), deci aici ar fi o fundătură.
            ->whereDoesntHave('enrollments', fn (Builder $sub) => $sub
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('academic_year_id', $yearId))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $count = (clone $query)->count();

        // Plafon de afișare: la un an nou, „toată școala" e neînmatriculată — lista rămâne
        // parcurgabilă, iar totalul spune restul.
        $students = array_values($query
            ->limit(60)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => (int) $student->id,
                'name' => (string) $student->full_name,
                'register' => $student->register_number !== null ? (string) $student->register_number : null,
                'enroll_url' => EnrollmentResource::getUrl('create', ['an' => $yearId, 'elev' => $student->id]),
            ])
            ->all());

        return ['count' => $count, 'students' => $students];
    }

    /**
     * Semnalele registrului pe anul activ — vizibile înaintea efectelor (elev „dispărut" din
     * cataloage, duplicat imposibil de recreat, interval negativ moștenit).
     *
     * @return list<array{level: string, text: string}>
     */
    public function integrity(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $signals = [];

        $unassigned = $this->unassigned()['count'];

        if ($unassigned > 0) {
            $signals[] = [
                'level' => 'warning',
                'text' => (string) trans_choice('panel.enrollments_nav.integrity.unassigned', $unassigned, ['count' => $unassigned]),
            ];
        }

        $archived = Enrollment::onlyTrashed()->where('academic_year_id', $yearId)->count();

        if ($archived > 0) {
            $signals[] = [
                'level' => 'info',
                'text' => (string) trans_choice('panel.enrollments_nav.integrity.archived', $archived, ['count' => $archived]),
            ];
        }

        // Intervale negative moștenite (garda de formular previne rândurile NOI).
        $broken = Enrollment::query()
            ->where('academic_year_id', $yearId)
            ->whereNotNull('enrolled_on')
            ->whereNotNull('left_on')
            ->whereColumn('left_on', '<', 'enrolled_on')
            ->count();

        if ($broken > 0) {
            $signals[] = [
                'level' => 'danger',
                'text' => (string) trans_choice('panel.enrollments_nav.integrity.broken_interval', $broken, ['count' => $broken]),
            ];
        }

        return $signals;
    }

    /**
     * Numărătoarea registrului clasei ACTIVE (activi/plecați) — antetul registrului o afișează
     * lângă diriginte, ca deschiderea clasei să spună imediat cât „cântărește".
     *
     * @return array{active: int, departed: int}
     */
    public function rosterCounts(): array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return ['active' => 0, 'departed' => 0];
        }

        $row = Enrollment::query()
            ->toBase()
            ->selectRaw('SUM(CASE WHEN left_on IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN left_on IS NOT NULL THEN 1 ELSE 0 END) AS departed')
            ->where('school_class_id', $class->getKey())
            ->first();

        return [
            'active' => (int) ($row->active ?? 0),
            'departed' => (int) ($row->departed ?? 0),
        ];
    }

    /** @return Collection<int|string, int> */
    private function classCountsByYear(): Collection
    {
        return $this->classCountsByYear ??= SchoolClass::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }
}
