<?php

namespace App\Filament\Resources\HomeworkCorrections\Pages;

use App\Enums\CorrectionStatus;
use App\Filament\Concerns\HasApprovalNavigator;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Coada corecțiilor de TEME ca navigator de aprobare (2026-07-16): „De procesat" / „Arhivă" →
 * carduri pe SOLICITANT → cererile lui, cu acțiunile existente (aprobă/respinge = Dir/PVD/AO).
 * Profesorul-autor își păstrează tabelul plat cu cererile proprii (+ retragere).
 */
class ListHomeworkCorrections extends ListRecords
{
    use HasApprovalNavigator;

    protected static string $resource = HomeworkCorrectionResource::class;

    protected string $view = 'filament.catalog.approvals-navigator';

    /** Solicitantul deschis (id „dorit" din URL, validat la citire prin cardurile vederii). */
    #[Url(as: 'solicitant', except: null)]
    public ?string $targetParam = null;

    /** Navigatorul e al celor care văd toată arhiva (super/dir/PVD/AO); restul = cererile proprii. */
    public function isQueueManagerView(): bool
    {
        return auth('web')->user()?->canViewCorrectionArchive() ?? false;
    }

    public function approvalHint(): string
    {
        return (string) __('panel.approval_nav.homework_hint');
    }

    public function approvalContextEyebrow(): string
    {
        return (string) __('panel.approval_nav.requester');
    }

    protected function pendingStatusValue(): string
    {
        return CorrectionStatus::Pending->value;
    }

    protected function approvalBaseQuery(): Builder
    {
        return HomeworkCorrectionResource::getEloquentQuery();
    }

    protected function buildApprovalCards(): array
    {
        return $this->buildRequesterCards();
    }

    protected function constrainToTarget(Builder $query, int $targetId): void
    {
        $query->where('requested_by_user_id', $targetId);
    }
}
