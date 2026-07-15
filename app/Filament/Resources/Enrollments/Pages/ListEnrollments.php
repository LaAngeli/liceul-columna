<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
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
                'stats' => $stats,
            ];
        }

        return $cards;
    }

    public function enrollmentsHint(): string
    {
        return (string) __('panel.catalog_nav.enrollments_hint');
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
