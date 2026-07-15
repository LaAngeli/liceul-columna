<?php

namespace App\Filament\Resources\GradeCorrections\Pages;

use App\Enums\CorrectionStatus;
use App\Filament\Concerns\HasApprovalNavigator;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Coada corecțiilor de NOTE ca navigator de aprobare (2026-07-16): „De procesat" / „Arhivă" →
 * carduri pe SOLICITANT (cu reperul de triaj: cea mai veche cerere) → cererile lui, cu acțiunile
 * existente. Profesorul-solicitant își păstrează tabelul plat cu cererile proprii.
 */
class ListGradeCorrections extends ListRecords
{
    use HasApprovalNavigator;

    protected static string $resource = GradeCorrectionResource::class;

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
        return (string) __('panel.approval_nav.grade_hint');
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
        return GradeCorrectionResource::getEloquentQuery();
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
