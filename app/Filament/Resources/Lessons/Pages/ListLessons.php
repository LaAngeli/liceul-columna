<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Orarul structurat, pe CLASE (navigatorul de configurare, 2026-07-16): pastile pe ani →
 * cardurile claselor anului — cu numărul de lecții/săptămână și avertisment „fără orar" unde
 * e gol (clasa se configurează de aici) → lecțiile clasei, cu adăugarea pre-completată.
 */
class ListLessons extends ListRecords
{
    protected static string $resource = LessonResource::class;

    protected string $view = 'filament.catalog.lessons-navigator';

    /** Anul școlar activ (id „dorit" din URL, validat la citire). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** Clasa al cărei orar e deschis (validată la citire). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** @var Collection<int|string, int>|null */
    private ?Collection $classCountsByYearMemo = null;

    private SchoolClass|false|null $activeClassMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Din orarul unei clase, adăugarea vine pre-completată (an + clasă).
                ->url(function (): string {
                    $class = $this->activeClass();

                    return LessonResource::getUrl('create', $class !== null
                        ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey()]
                        : []);
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openYear(int|string $id): void
    {
        if ($this->classCountsByYear()->has((int) $id)) {
            $this->yearParam = (string) (int) $id;
        }
    }

    public function openClass(int|string $id): void
    {
        if (SchoolClass::query()->whereKey((int) $id)->exists()) {
            $this->classParam = (string) (int) $id;
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

        $newest = $visible->keys()->map(fn ($id): int => (int) $id)->sortDesc()->first();

        return $newest;
    }

    /** Constrângerea lecțiilor pe clasa activă (apelată din LessonsTable). */
    public function hasClassContext(): bool
    {
        return $this->activeClass() !== null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyClassContext(Builder $query): Builder
    {
        $class = $this->activeClass();

        return $class !== null
            ? $query->where('school_class_id', $class->getKey())
            : $query;
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Pastilele anilor cu clase, badge = lecțiile programate în anul respectiv.
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $classCounts = $this->classCountsByYear();

        if ($classCounts->isEmpty()) {
            return [];
        }

        $lessonCounts = Lesson::query()
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
                'count' => (int) ($lessonCounts->get($year->id) ?? 0),
            ])
            ->all();
    }

    /**
     * Cardurile claselor anului activ: lecțiile pe săptămână; „fără orar" = avertisment
     * (de aici se configurează).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}>
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

        $lessonCounts = Lesson::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->whereIn('school_class_id', $classes->pluck('id')->all())
            ->groupBy('school_class_id')
            ->pluck('aggregate', 'school_class_id');

        $cards = [];

        foreach ($classes as $class) {
            $lessons = (int) ($lessonCounts->get($class->id) ?? 0);

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'stats' => [
                    $lessons > 0
                        ? (string) trans_choice('panel.config_nav.lessons_per_week', $lessons, ['count' => $lessons])
                        : (string) __('panel.config_nav.no_timetable'),
                ],
                'badge' => $lessons === 0 ? (string) __('panel.config_nav.no_timetable_badge') : null,
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.lessons_hint');
    }

    /** @return Collection<int|string, int> */
    private function classCountsByYear(): Collection
    {
        return $this->classCountsByYearMemo ??= SchoolClass::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }
}
