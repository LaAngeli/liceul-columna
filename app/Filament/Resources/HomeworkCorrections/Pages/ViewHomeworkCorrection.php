<?php

namespace App\Filament\Resources\HomeworkCorrections\Pages;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Models\HomeworkCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * FIȘA cererii de corecție — locul unde se JUDECĂ (spec §3.1): motivul integral, propunerea
 * vechi → nou pe fiecare câmp, contextul temei vizate și cronologia procesului, cu Aprobă /
 * Respinge chiar aici. Lista rămâne coada; decizia se ia cu tot contextul în față —
 * respingerea cere obligatoriu motiv (solicitantul e notificat și altfel ar redepune orbește).
 *
 * @property HomeworkCorrection $record
 */
class ViewHomeworkCorrection extends ViewRecord
{
    protected static string $resource = HomeworkCorrectionResource::class;

    protected string $view = 'filament.approvals.homework-correction-details';

    public function getTitle(): string
    {
        $subject = $this->record->homeworkAssignment?->subject_name;

        return __('panel.homework_correction_view.title', [
            'subject' => $subject !== null ? ContentTranslator::subject($subject) : '—',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('panel.actions.approve.label'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->record->isPending() && $this->canJudge())
                ->modalHeading(__('panel.actions.approve.label'))
                ->modalDescription(__('panel.actions.homework_correction.approve_description'))
                ->modalSubmitActionLabel(__('panel.actions.approve.label'))
                ->schema([
                    Textarea::make('review_note')
                        ->label(__('panel.common.review_note'))
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->record->approve((int) auth()->id(), $data['review_note'] ?? null);
                    $this->record->refresh();

                    Notification::make()->success()->title(__('panel.actions.approve.success'))->send();
                }),

            Action::make('reject')
                ->label(__('panel.actions.reject.label'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (): bool => $this->record->isPending() && $this->canJudge())
                ->modalHeading(__('panel.actions.reject.label'))
                ->modalSubmitActionLabel(__('panel.actions.reject.label'))
                ->schema([
                    // Motiv OBLIGATORIU: solicitantul primește verdictul și trebuie să înțeleagă DE CE.
                    Textarea::make('review_note')
                        ->label(__('panel.common.rejection_reason'))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->record->reject((int) auth()->id(), $data['review_note'] ?? null);
                    $this->record->refresh();

                    Notification::make()->warning()->title(__('panel.actions.reject.success'))->send();
                }),

            // Solicitantul își poate RETRAGE cererea cât timp e în așteptare.
            Action::make('withdraw')
                ->label(__('panel.actions.request_correction.withdraw'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('panel.actions.request_correction.withdraw_heading'))
                ->modalDescription(__('panel.actions.request_correction.withdraw_description'))
                ->modalSubmitActionLabel(__('panel.actions.request_correction.withdraw_submit'))
                ->visible(fn (): bool => $this->record->isPending()
                    && $this->record->requested_by_user_id === auth('web')->id())
                ->action(function (): void {
                    $this->record->withdraw();
                    $this->record->refresh();

                    Notification::make()->success()->title(__('panel.actions.request_correction.withdraw_success'))->send();
                }),
        ];
    }

    public function canJudge(): bool
    {
        return auth('web')->user()?->canApproveHomeworkCorrections() ?? false;
    }

    /**
     * Propunerea, câmp cu câmp: doar cele atinse (new_* non-null), cu vechi → nou.
     *
     * @return list<array{label: string, old: string|null, new: string}>
     */
    public function proposedChanges(): array
    {
        $changes = [];

        foreach ([
            'topic' => [(string) __('panel.forms.homework.topic'), $this->record->old_topic, $this->record->new_topic],
            'required' => [(string) __('panel.forms.homework.required_task'), $this->record->old_required_task, $this->record->new_required_task],
            'optional' => [(string) __('panel.forms.homework.optional_task'), $this->record->old_optional_task, $this->record->new_optional_task],
        ] as [$label, $old, $new]) {
            if ($new !== null) {
                $changes[] = ['label' => $label, 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Cronologia procesului: depunerea, apoi — dacă există — verdictul/retragerea/expirarea,
     * cu autor, moment și nota integrală.
     *
     * @return list<array{label: string, actor: string|null, at: string, note: string|null, color: string}>
     */
    public function timeline(): array
    {
        $entries = [[
            'label' => (string) __('panel.homework_correction_view.submitted'),
            'actor' => $this->record->requestedBy?->name,
            'at' => $this->record->created_at->translatedFormat('d.m.Y H:i'),
            'note' => null,
            'color' => 'bg-primary-500',
        ]];

        if ($this->record->reviewed_at !== null) {
            $entries[] = [
                'label' => $this->record->status->getLabel(),
                'actor' => $this->record->reviewedBy?->name,
                'at' => $this->record->reviewed_at->translatedFormat('d.m.Y H:i'),
                'note' => $this->record->review_note,
                'color' => match ($this->record->status) {
                    CorrectionStatus::Approved => 'bg-success-500',
                    CorrectionStatus::Rejected => 'bg-danger-500',
                    default => 'bg-gray-400',
                },
            ];
        }

        return $entries;
    }
}
