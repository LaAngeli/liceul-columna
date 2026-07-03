<?php

namespace App\Filament\Widgets;

use App\Enums\CorrectionStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Models\AbsenceMotivation;
use App\Models\DocumentRequest;
use App\Models\GradeCorrection;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Hub-ul de aprobări PENDING ale celui logat: corecții note + motivări absențe + cereri documente
 * într-un singur loc, fiecare cu link la coada filtrată. Mută în vedere ce era doar badge în
 * sidebar. Nume distinct ca să nu se ciocnească cu widget-urile existente; sort -1 (după
 * widget-urile de queue ClassesNeedingHomeroom/SchedulesToComplete, înaintea AudiencesPendingAssignment).
 */
class PendingApprovalsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    // Polling rapid (30s): coada de aprobări scade vizibil când mai mulți aprobă în paralel.
    // Aliniat cu polling-ul clopoțelului de notificări din panou.
    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->canApproveGradeCorrections()
            || AbsenceMotivationResource::canAccess()
            || DocumentRequestResource::canAccess();
    }

    protected function getStats(): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return [];
        }

        $stats = [];

        if ($user->canApproveGradeCorrections()) {
            $count = GradeCorrection::query()
                ->where('status', CorrectionStatus::Pending)
                ->count();
            $stats[] = Stat::make(__('panel.widgets.pending_approvals.grade_corrections.title'), $count)
                ->description($count > 0
                    ? __('panel.widgets.pending_approvals.grade_corrections.pending')
                    : __('panel.widgets.pending_approvals.grade_corrections.empty'))
                ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->color($count > 0 ? 'warning' : 'success')
                ->url(GradeCorrectionResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => CorrectionStatus::Pending->value]],
                ]));
        }

        if (AbsenceMotivationResource::canAccess()) {
            // Sursă unică memoizată (badge + badgeColor + widget consumă același rezultat).
            // Un singur ->get() cu coloane minimale → isOverdue() filtrat în PHP (termenul de
            // 2 zile lucrătoare folosește WorkingDays — nu se traduce simplu în SQL).
            $pending = AbsenceMotivationResource::pendingMotivations();
            $count = $pending->count();
            $overdue = $pending
                ->filter(fn (AbsenceMotivation $m): bool => $m->isOverdue())
                ->count();
            $stats[] = Stat::make(__('panel.widgets.pending_approvals.absence_motivations.title'), $count)
                ->description($overdue > 0
                    ? __('panel.widgets.pending_approvals.absence_motivations.overdue', ['count' => $overdue])
                    : ($count > 0
                        ? __('panel.widgets.pending_approvals.absence_motivations.pending')
                        : __('panel.widgets.pending_approvals.absence_motivations.empty')))
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color($overdue > 0 ? 'danger' : ($count > 0 ? 'warning' : 'success'))
                ->url(AbsenceMotivationResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => RequestStatus::Pending->value]],
                ]));
        }

        if (DocumentRequestResource::canAccess()) {
            $count = DocumentRequest::query()
                ->where('status', RequestStatus::Pending)
                ->count();
            $stats[] = Stat::make(__('panel.widgets.pending_approvals.document_requests.title'), $count)
                ->description($count > 0
                    ? __('panel.widgets.pending_approvals.document_requests.pending')
                    : __('panel.widgets.pending_approvals.document_requests.empty'))
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color($count > 0 ? 'warning' : 'success')
                ->url(DocumentRequestResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => RequestStatus::Pending->value]],
                ]));
        }

        return $stats;
    }
}
