<?php

namespace App\Filament\Resources\GradeCorrections\Pages;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use App\Support\SchoolCalendar;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FIȘA cererii de corecție de notă — locul unde se JUDECĂ (spec §3.1): valoarea actuală față de
 * cea propusă dintr-o privire, motivul integral, contextul notei (elev / disciplină / profesor /
 * tip evaluare), contestația-sursă a familiei dacă există, ISTORICUL notei (corecții anterioare +
 * jurnalul de modificări din audit) și cronologia cererii — cu Aprobă / Respinge chiar aici.
 * Respingerea cere obligatoriu motiv (solicitantul e notificat).
 *
 * @property GradeCorrection $record
 */
class ViewGradeCorrection extends ViewRecord
{
    protected static string $resource = GradeCorrectionResource::class;

    protected string $view = 'filament.approvals.grade-correction-details';

    public function getTitle(): string
    {
        return __('panel.grade_correction_view.title', [
            'student' => $this->record->grade?->student->full_name ?? '—',
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
                ->modalDescription(__('panel.actions.approve_bulk.description'))
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
        return auth('web')->user()?->canApproveGradeCorrections() ?? false;
    }

    /** Valoarea afișabilă (numerică sau calificativ). */
    public function displayValue(?string $value, ?string $calificativ): string
    {
        if ($value !== null) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return $calificativ ?? '—';
    }

    /**
     * Contextul notei vizate, gata de afișat.
     *
     * @return array{student: string|null, student_url: string|null, subject: string|null, teacher: string|null, graded_on: string|null, evaluation_type: string|null, annulled: bool}|null
     */
    public function gradeContext(): ?array
    {
        $grade = $this->record->grade;

        if ($grade === null) {
            return null;
        }

        return [
            'student' => $grade->student->full_name,
            'student_url' => StudentResource::getUrl('view', ['record' => $grade->student_id]),
            'subject' => ContentTranslator::subject($grade->subject->name),
            'teacher' => $grade->teacher?->full_name,
            'graded_on' => $grade->graded_on->translatedFormat('d.m.Y'),
            'evaluation_type' => $grade->evaluation_type->getLabel(),
            'annulled' => $grade->isAnnulled(),
        ];
    }

    /** Contestația familiei din care a pornit corecția (fluxul contestație→corecție), dacă există. */
    public function contestationUrl(): ?string
    {
        return $this->record->document_request_id !== null
            ? DocumentRequestResource::getUrl('view', ['record' => $this->record->document_request_id])
            : null;
    }

    /**
     * ISTORICUL notei: celelalte cereri de corecție pe aceeași notă (fără cea curentă).
     *
     * @return list<array{change: string, status_label: string, status_color: string, reviewer: string|null, at: string, url: string}>
     */
    public function priorCorrections(): array
    {
        $entries = GradeCorrection::query()
            ->with('reviewedBy')
            ->where('grade_id', $this->record->grade_id)
            ->whereKeyNot($this->record->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GradeCorrection $correction): array => [
                'change' => $this->displayValue($correction->old_value, $correction->old_calificativ)
                    .' → '.$this->displayValue($correction->new_value, $correction->new_calificativ),
                'status_label' => $correction->status->getLabel(),
                'status_color' => $correction->status->color(),
                'reviewer' => $correction->reviewedBy?->name,
                'at' => (string) SchoolCalendar::local($correction->created_at)?->translatedFormat('d.m.Y H:i'),
                'url' => GradeCorrectionResource::getUrl('view', ['record' => $correction]),
            ])
            ->all();

        return array_values($entries);
    }

    /**
     * Jurnalul de modificări al NOTEI (nota e auditabilă): schimbările de valoare/calificativ/
     * anulare, cu autor și moment — trasabilitatea completă cerută de §7/L133.
     *
     * @return list<array{summary: string, actor: string|null, at: string}>
     */
    public function gradeAuditTrail(): array
    {
        $rows = DB::table('audits')
            ->leftJoin('users', 'users.id', '=', 'audits.user_id')
            ->where('audits.auditable_type', Grade::class)
            ->where('audits.auditable_id', $this->record->grade_id)
            ->orderByDesc('audits.created_at')
            ->limit(10)
            ->get(['audits.event', 'audits.old_values', 'audits.new_values', 'audits.created_at', 'users.name']);

        $entries = [];

        foreach ($rows as $row) {
            $old = json_decode((string) $row->old_values, true) ?: [];
            $new = json_decode((string) $row->new_values, true) ?: [];

            $summary = $this->auditSummary((string) $row->event, $old, $new);

            if ($summary === null) {
                continue;
            }

            $entries[] = [
                'summary' => $summary,
                'actor' => $row->name !== null ? (string) $row->name : null,
                'at' => (string) SchoolCalendar::local(Carbon::parse((string) $row->created_at))?->translatedFormat('d.m.Y H:i'),
            ];
        }

        return $entries;
    }

    /**
     * Rezumatul lizibil al unei intrări de audit pe notă; null = schimbare irelevantă fișei
     * (ex. doar timestamp-uri).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function auditSummary(string $event, array $old, array $new): ?string
    {
        if ($event === 'created') {
            return (string) __('panel.grade_correction_view.audit_created', [
                'value' => $this->displayValue(
                    isset($new['value']) ? (string) $new['value'] : null,
                    isset($new['calificativ']) ? (string) $new['calificativ'] : null,
                ),
            ]);
        }

        if (array_key_exists('annulled_at', $new) && $new['annulled_at'] !== null) {
            return (string) __('panel.grade_correction_view.audit_annulled');
        }

        if (array_key_exists('value', $new) || array_key_exists('calificativ', $new)) {
            return (string) __('panel.grade_correction_view.audit_value_changed', [
                'old' => $this->displayValue(
                    isset($old['value']) ? (string) $old['value'] : null,
                    isset($old['calificativ']) ? (string) $old['calificativ'] : null,
                ),
                'new' => $this->displayValue(
                    isset($new['value']) ? (string) $new['value'] : null,
                    isset($new['calificativ']) ? (string) $new['calificativ'] : null,
                ),
            ]);
        }

        return null;
    }

    /**
     * Cronologia CERERII: depunerea, apoi verdictul/retragerea/expirarea, cu nota integrală.
     *
     * @return list<array{label: string, actor: string|null, at: string, note: string|null, color: string}>
     */
    public function timeline(): array
    {
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
