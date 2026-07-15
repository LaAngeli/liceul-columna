<?php

namespace App\Filament\Resources\AbsenceMotivations\Pages;

use App\Enums\RequestStatus;
use App\Filament\Concerns\HasApprovalNavigator;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Models\AbsenceMotivation;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Coada motivărilor ca navigator de aprobare (2026-07-16): „De procesat" / „Arhivă" → carduri pe
 * CLASA CURENTĂ a elevului (diriginte + „peste termen" ca badge de urgență) → cererile clasei,
 * cu acțiunile existente. Toți cei cu acces aici sunt validatori (canAccess), deci navigatorul e
 * pentru toți; dirigintele cu o singură clasă aterizează DIRECT în coada ei (fallback).
 */
class ListAbsenceMotivations extends ListRecords
{
    use HasApprovalNavigator;

    protected static string $resource = AbsenceMotivationResource::class;

    protected string $view = 'filament.catalog.approvals-navigator';

    /** Clasa deschisă (id „dorit" din URL, validat la citire prin cardurile vederii). */
    #[Url(as: 'clasa', except: null)]
    public ?string $targetParam = null;

    public function isQueueManagerView(): bool
    {
        // canAccess() al resursei lasă doar validatorii (administrație / diriginți / vicedir).
        return true;
    }

    public function approvalHint(): string
    {
        return (string) __('panel.approval_nav.motivations_hint');
    }

    public function approvalContextEyebrow(): string
    {
        return (string) __('panel.fields.class');
    }

    protected function pendingStatusValue(): string
    {
        return RequestStatus::Pending->value;
    }

    protected function approvalBaseQuery(): Builder
    {
        return AbsenceMotivationResource::getEloquentQuery();
    }

    /**
     * Coada exclude cererile elevilor ARHIVAȚI — aliniat cu badge-ul de sidebar
     * (AbsenceMotivationResource::pendingMotivations); ele rămân vizibile în arhivă.
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToQueue(Builder $query): void
    {
        $query->where('status', $this->pendingStatusValue())
            ->whereHas('student', fn (Builder $q) => $q->whereNull('deleted_at'));
    }

    /** Dirigintele cu o singură clasă în vedere aterizează direct în coada ei. */
    protected function fallbackTargetId(): ?int
    {
        $cards = $this->approvalCards();

        return count($cards) === 1 ? $cards[0]['id'] : null;
    }

    /**
     * Cardurile claselor: cererile vederii active, grupate pe clasa CURENTĂ a elevului
     * (înmatricularea cea mai recentă — aceeași regulă ca scoping-ul și dreptul de validare).
     * Elevii fără nicio înmatriculare intră în cardul „Fără clasă curentă" (id 0).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}>
     */
    protected function buildApprovalCards(): array
    {
        $query = $this->approvalBaseQuery();

        $this->isArchiveView() ? $this->constrainToArchive($query) : $this->constrainToQueue($query);

        /** @var Collection<int, AbsenceMotivation> $motivations */
        $motivations = $query->get();

        if ($motivations->isEmpty()) {
            return [];
        }

        $classByStudent = Enrollment::query()
            ->toBase()
            ->selectRaw('student_id, school_class_id')
            ->whereIn('student_id', $motivations->pluck('student_id')->unique()->all())
            ->whereRaw('enrollments.academic_year_id = (select max(e2.academic_year_id) from enrollments e2 where e2.student_id = enrollments.student_id and e2.deleted_at is null)')
            ->pluck('school_class_id', 'student_id');

        /** @var array<int, array{total: int, overdue: int}> $groups */
        $groups = [];

        foreach ($motivations as $motivation) {
            $classId = (int) ($classByStudent->get($motivation->student_id) ?? 0);

            $groups[$classId] ??= ['total' => 0, 'overdue' => 0];
            $groups[$classId]['total']++;

            if (! $this->isArchiveView() && $motivation->isOverdue()) {
                $groups[$classId]['overdue']++;
            }
        }

        $classes = SchoolClass::query()
            ->with('homeroomTeacher')
            ->whereKey(array_filter(array_keys($groups)))
            ->get()
            ->keyBy('id');

        // Ordinea claselor din catalog; „Fără clasă curentă" (id 0) rămâne la coadă.
        $orderedClassIds = $classes
            ->sortBy([['grade_level', 'asc'], ['name', 'asc'], ['section', 'asc']])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (isset($groups[0])) {
            $orderedClassIds[] = 0;
        }

        $cards = [];

        foreach ($orderedClassIds as $classId) {
            $counts = $groups[$classId];
            $class = $classes->get($classId);

            $cards[] = [
                'id' => $classId,
                'title' => $class !== null
                    ? trim($class->name.' '.($class->section ?? ''))
                    : (string) __('panel.approval_nav.no_class'),
                'subtitle' => $class?->homeroomTeacher?->full_name,
                'stats' => [(string) trans_choice('panel.approval_nav.requests', $counts['total'], ['count' => $counts['total']])],
                'badge' => $counts['overdue'] > 0
                    ? (string) __('panel.approval_nav.overdue_count', ['count' => $counts['overdue']])
                    : null,
            ];
        }

        return $cards;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function constrainToTarget(Builder $query, int $targetId): void
    {
        if ($targetId === 0) {
            $query->whereDoesntHave('student.enrollments');

            return;
        }

        // Aceeași regulă „clasa curentă = înmatricularea cea mai recentă" ca în scoping.
        $query->whereHas('student.enrollments', fn (Builder $sub) => $sub
            ->where('school_class_id', $targetId)
            ->whereRaw('enrollments.academic_year_id = (select max(e2.academic_year_id) from enrollments e2 where e2.student_id = enrollments.student_id and e2.deleted_at is null)'));
    }
}
