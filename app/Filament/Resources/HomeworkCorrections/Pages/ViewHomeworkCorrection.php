<?php

namespace App\Filament\Resources\HomeworkCorrections\Pages;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Models\HomeworkCorrection;
use App\Support\ContentTranslator;
use App\Support\SchoolCalendar;
use Filament\Resources\Pages\ViewRecord;

/**
 * FIȘA unei corecții din registru (v2, 2026-07-31) — read-only: ce s-a schimbat (vechi → nou pe
 * fiecare câmp), contextul temei și cronologia. Judecata (Aprobă/Respinge/Retrage) a fost
 * demontată odată cu fluxul de aprobare: corecția e directă, aplicată la editare.
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

    /**
     * Schimbarea, câmp cu câmp: doar cele atinse (new_* non-null), cu vechi → nou.
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
     * Cronologia: la corecțiile DIRECTE — o singură intrare („aplicată de X"); la rândurile
     * istorice ale fluxului vechi — depunerea, apoi verdictul/retragerea/expirarea, cu autor,
     * moment și nota integrală.
     *
     * @return list<array{label: string, actor: string|null, at: string, note: string|null, color: string}>
     */
    public function timeline(): array
    {
        if ($this->record->isDirect()) {
            return [[
                'label' => (string) __('panel.homework_correction_view.applied_direct'),
                'actor' => $this->record->requestedBy?->name,
                'at' => (string) SchoolCalendar::local($this->record->created_at)?->translatedFormat('d.m.Y H:i'),
                'note' => $this->record->review_note,
                'color' => 'bg-success-500',
            ]];
        }

        $entries = [[
            'label' => (string) __('panel.homework_correction_view.submitted'),
            'actor' => $this->record->requestedBy?->name,
            'at' => (string) SchoolCalendar::local($this->record->created_at)?->translatedFormat('d.m.Y H:i'),
            'note' => null,
            'color' => 'bg-primary-500',
        ]];

        if ($this->record->reviewed_at !== null) {
            $entries[] = [
                'label' => $this->record->status->getLabel(),
                'actor' => $this->record->reviewedBy?->name,
                'at' => (string) SchoolCalendar::local($this->record->reviewed_at)?->translatedFormat('d.m.Y H:i'),
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
