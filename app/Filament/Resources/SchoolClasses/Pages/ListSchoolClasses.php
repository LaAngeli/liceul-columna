<?php

namespace App\Filament\Resources\SchoolClasses\Pages;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Clase" = navigator cu CARDURI (2026-07-15, cerința beneficiarului: „ca la Elevi"),
 * nu tabel: pastile pe ani școlari (anul curent implicit) → cardurile claselor vizibile
 * (profesorul/dirigintele doar clasele lui — scoping-ul resursei; administrația toate), cu
 * diriginte, elevi și sărituri directe către Elevi / Note / Absențe / Teme pe contextul clasei.
 * Configuratorii au „Editare" pe card (acolo trăiesc înmatriculările, restaurarea și ștergerea),
 * iar administrația și vederea „Șterse" pentru clasele arhivate. Tabelul resursei nu se mai
 * randează; excepția „fără diriginte" apare ca badge pe card (aliniat cu scopeWithoutHomeroom).
 */
class ListSchoolClasses extends ListRecords
{
    protected static string $resource = SchoolClassResource::class;

    protected string $view = 'filament.catalog.classes-navigator';

    /** Anul școlar activ (id „dorit" din URL, validat la citire prin anii vizibili). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** Vederea claselor ȘTERSE (doar administrația) — restaurarea se face din Editare. */
    #[Url(as: 'sterse', except: null)]
    public ?string $trashedMode = null;

    /** @var Collection<int|string, int>|null clase vizibile per an școlar (memoizat pe instanță) */
    private ?Collection $visibleYearCounts = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('trashed')
                ->label(__('panel.catalog_nav.classes_trashed'))
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn (): bool => $this->canUseTrashedView()
                    && ! $this->isTrashedMode()
                    && $this->trashedQuery()->exists())
                ->action(function (): void {
                    $this->trashedMode = '1';
                }),
            CreateAction::make(),
        ];
    }

    public function isTrashedMode(): bool
    {
        return $this->trashedMode === '1' && $this->canUseTrashedView();
    }

    public function leaveTrashed(): void
    {
        $this->trashedMode = null;
    }

    public function openYear(int|string $id): void
    {
        $id = (int) $id;

        if ($this->visibleYearCounts()->has($id)) {
            $this->yearParam = (string) $id;
        }
    }

    /** Anul activ: cel cerut prin URL dacă e vizibil, altfel anul CURENT, altfel cel mai recent. */
    public function activeYearId(): ?int
    {
        $visible = $this->visibleYearCounts();

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
     * Pastilele anilor școlari cu clase vizibile (cei mai noi întâi) + numărul de clase.
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $counts = $this->visibleYearCounts();

        if ($counts->isEmpty()) {
            return [];
        }

        return AcademicYear::query()
            ->whereKey($counts->keys()->all())
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => (int) $year->id,
                'label' => (string) $year->name,
                'count' => (int) $counts->get($year->id),
            ])
            ->all();
    }

    public function classesHint(): string
    {
        return (string) __((auth('web')->user()?->isAdministrator() ?? false)
            ? 'panel.catalog_nav.classes_hint_all'
            : 'panel.catalog_nav.classes_hint_own');
    }

    /**
     * Cardurile claselor din anul activ: diriginte + elevi + badge „Clasa mea" / „Fără diriginte"
     * + sărituri directe pe contextul clasei; „Editare" doar pentru cine poate configura.
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, missing_homeroom: bool, badge: string|null, students: string, links: array<string, string>, edit_url: string|null}>
     */
    public function classCards(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $enrollments = $this->enrollmentCountsByClass();
        $viewerHomeroomIds = $this->viewerHomeroomIds();
        $user = auth('web')->user();

        $cards = [];

        $classes = $this->scopedClassQuery()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $yearId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        foreach ($classes as $class) {
            $students = (int) ($enrollments->get($class->id)->aggregate ?? 0);
            $context = ['clasa' => $class->id];

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                // Aliniat cu SchoolClass::scopeWithoutHomeroom(): fără diriginte FUNCȚIONAL
                // (FK gol SAU fișă arhivată — relația exclude soft-deleted) ȘI cu elevi.
                'missing_homeroom' => $class->homeroomTeacher === null && $students > 0,
                'badge' => in_array((int) $class->id, $viewerHomeroomIds, true)
                    ? (string) __('panel.catalog_nav.homeroom')
                    : null,
                'students' => (string) trans_choice('panel.catalog_nav.students', $students, ['count' => $students]),
                'links' => [
                    (string) __('panel.resources.students.label') => StudentResource::getUrl('index', $context),
                    (string) __('panel.resources.grades.label') => GradeResource::getUrl('index', $context),
                    (string) __('panel.resources.absences.label') => AbsenceResource::getUrl('index', $context),
                    (string) __('panel.resources.homework.label') => HomeworkAssignmentResource::getUrl('index', $context),
                ],
                'edit_url' => $user?->can('update', $class)
                    ? SchoolClassResource::getUrl('edit', ['record' => $class])
                    : null,
            ];
        }

        return $cards;
    }

    /**
     * Cardurile claselor șterse (cele mai recente întâi) — fără sărituri în catalog (contextele
     * validate exclud clasele șterse); restaurarea/ștergerea definitivă trăiesc în Editare.
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, deleted: string, edit_url: string|null}>
     */
    public function trashedCards(): array
    {
        if (! $this->isTrashedMode()) {
            return [];
        }

        $user = auth('web')->user();

        $cards = [];

        $classes = $this->trashedQuery()
            ->with(['homeroomTeacher', 'academicYear'])
            ->orderByDesc('deleted_at')
            ->get();

        foreach ($classes as $class) {
            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->academicYear->name ?? null,
                'deleted' => (string) __('panel.catalog_nav.deleted_on', [
                    'date' => $class->deleted_at?->translatedFormat('j F Y') ?? (string) __('panel.common.dash'),
                ]),
                'edit_url' => $user?->can('update', $class)
                    ? SchoolClassResource::getUrl('edit', ['record' => $class])
                    : null,
            ];
        }

        return $cards;
    }

    /**
     * Interogarea CONCRETĂ (Builder<SchoolClass>) restrânsă la perimetrul resursei — scoping-ul
     * pe rol rămâne definit o singură dată, în SchoolClassResource::getEloquentQuery().
     *
     * @return Builder<SchoolClass>
     */
    private function scopedClassQuery(): Builder
    {
        return SchoolClass::query()->whereIn(
            'id',
            SchoolClassResource::getEloquentQuery()->select('id'),
        );
    }

    /** @return Builder<SchoolClass> */
    private function trashedQuery(): Builder
    {
        // Doar administrația ajunge aici (canUseTrashedView) — nu e nevoie de scoping suplimentar.
        return SchoolClass::query()->onlyTrashed();
    }

    /** @return Collection<int|string, int> */
    private function visibleYearCounts(): Collection
    {
        return $this->visibleYearCounts ??= SchoolClassResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }

    /** @return Collection<int|string, \stdClass> nr. de elevi per clasă (o interogare) */
    private function enrollmentCountsByClass(): Collection
    {
        return Enrollment::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');
    }

    /** @return array<int, int> */
    private function viewerHomeroomIds(): array
    {
        return auth('web')->user()?->teacher?->homeroomSchoolClassIds() ?? [];
    }

    private function canUseTrashedView(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
    }
}
