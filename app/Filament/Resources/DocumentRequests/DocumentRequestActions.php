<?php

namespace App\Filament\Resources\DocumentRequests;

use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\GradingType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Acțiunile de procesare ale unei cereri tipice — O SINGURĂ definiție, montată și pe rândurile
 * tabelului, și în antetul fișei cererii. Fiecare decizie poartă CONTEXTUL cererii (elevul,
 * depunătorul, comentariul familiei) și poate lăsa un comentariu de procesare pe care familia
 * îl vede în cabinet (feedback beneficiar: „Deschide corecție" nu spunea despre ce corecție e
 * vorba și cui i se afiliază).
 */
class DocumentRequestActions
{
    /** PDF-ul generat al cererii (rută autentificată — conține PII de minor). */
    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label(__('panel.actions.pdf.label'))
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn (DocumentRequest $record): bool => $record->pdf_path !== null)
            ->url(
                fn (DocumentRequest $record): string => route('cabinet.requests.pdf', $record),
                shouldOpenInNewTab: true,
            );
    }

    /**
     * Marchează cererea procesată, cu comentariu OPȚIONAL pentru familie (vizibil în cabinet) —
     * „adeverința e gata la secretariat", „ședința e programată miercuri la 14:00" etc.
     */
    public static function process(): Action
    {
        return Action::make('process')
            ->label(__('panel.actions.process.label'))
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (DocumentRequest $record): bool => $record->status === RequestStatus::Pending)
            ->modalHeading(__('panel.actions.process.heading'))
            ->modalDescription(fn (DocumentRequest $record): string => self::requestSummary($record))
            ->modalSubmitActionLabel(__('panel.actions.process.label'))
            ->schema([
                Textarea::make('review_note')
                    ->label(__('panel.actions.process.note'))
                    ->helperText(__('panel.actions.process.note_hint'))
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (DocumentRequest $record, array $data): void {
                $record->markProcessed((int) auth()->id(), filled($data['review_note'] ?? null) ? (string) $data['review_note'] : null);

                Notification::make()->success()->title(__('panel.actions.process.success'))->send();
            });
    }

    /** Respinge cererea — motivul (opțional) ajunge la familie, în cabinet. */
    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('panel.actions.reject_request.label'))
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (DocumentRequest $record): bool => $record->status === RequestStatus::Pending)
            ->modalHeading(fn (): string => __('panel.actions.reject_request.heading'))
            ->modalDescription(fn (DocumentRequest $record): string => self::requestSummary($record))
            ->schema([
                Textarea::make('review_note')
                    ->label(__('panel.common.rejection_reason'))
                    ->helperText(__('panel.actions.reject_request.note_hint'))
                    ->rows(3)
                    ->maxLength(255),
            ])
            ->action(function (DocumentRequest $record, array $data): void {
                $record->markRejected((int) auth()->id(), $data['review_note'] ?? null);

                Notification::make()->warning()->title(__('panel.actions.reject_request.success'))->send();
            });
    }

    /**
     * Fluxul contestație→corecție (#36): contestația familiei NU rămâne un PDF mort —
     * administrația o transformă într-o cerere formală de corecție (judecată apoi de aprobatorii
     * din „Corecții note"), iar cererea e închisă cu trimitere la corecție. Modalul poartă
     * CONTEXTUL: al cui e contestația, cine a depus-o și ce a scris familia.
     */
    public static function openCorrection(): Action
    {
        return Action::make('openCorrection')
            ->label(__('panel.actions.open_correction.label'))
            ->icon('heroicon-o-scale')
            ->color('warning')
            ->modalSubmitActionLabel(__('panel.actions.open_correction.submit'))
            ->visible(fn (DocumentRequest $record): bool => $record->type === DocumentRequestType::Contestatie
                && $record->status === RequestStatus::Pending)
            ->modalHeading(fn (DocumentRequest $record): string => __('panel.actions.open_correction.heading_for', [
                'student' => (string) $record->student?->full_name,
            ]))
            // Contextul cererii chiar în modal: cine a depus, când, pentru cine — plus procedura.
            ->modalDescription(fn (DocumentRequest $record): string => self::requestSummary($record)
                .' '.__('panel.actions.open_correction.description'))
            ->schema([
                // Ce a scris familia — read-only, ca decizia să se ia CU cererea în față.
                Textarea::make('family_details')
                    ->label(__('panel.actions.open_correction.family_details'))
                    ->default(fn (DocumentRequest $record): string => (string) ($record->payload['details'] ?? ''))
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(3)
                    ->visible(fn (DocumentRequest $record): bool => filled($record->payload['details'] ?? null)),
                Select::make('grade_id')
                    ->label(__('panel.actions.open_correction.grade'))
                    ->options(fn (DocumentRequest $record): array => self::contestableGradeOptions($record))
                    ->required()
                    ->live(),
                // Perechea notă/calificativ urmează disciplina notei ALESE (ca la profesor);
                // `requiredWithout` reciproc blochează „nicio propunere de valoare".
                TextInput::make('new_value')
                    ->label(__('panel.actions.request_correction.new_value'))
                    ->validationAttribute(__('panel.actions.request_correction.new_value'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->visible(fn (Get $get): bool => self::selectedGradeIsNumeric($get('grade_id')))
                    ->requiredWithout('new_calificativ'),
                TextInput::make('new_calificativ')
                    ->label(__('panel.actions.request_correction.new_calificativ'))
                    ->validationAttribute(__('panel.actions.request_correction.new_calificativ'))
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => ! self::selectedGradeIsNumeric($get('grade_id')))
                    ->requiredWithout('new_value'),
                Textarea::make('reason')
                    ->label(__('panel.actions.request_correction.reason'))
                    ->default(fn (DocumentRequest $record): string => (string) ($record->payload['details'] ?? ''))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (DocumentRequest $record, array $data): void {
                $grade = Grade::query()->findOrFail((int) $data['grade_id']);

                try {
                    $correction = GradeCorrection::create([
                        'grade_id' => $grade->id,
                        'requested_by_user_id' => auth()->id(),
                        'document_request_id' => $record->id,
                        'old_value' => $grade->value,
                        'new_value' => $data['new_value'] ?? null,
                        'old_calificativ' => $grade->calificativ,
                        'new_calificativ' => $data['new_calificativ'] ?? null,
                        'reason' => $data['reason'],
                    ]);
                } catch (ValidationException) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.actions.request_correction.already_pending'))
                        ->send();

                    return;
                }

                $record->markProcessed(
                    (int) auth()->id(),
                    __('panel.actions.open_correction.processed_note', ['id' => $correction->id]),
                );

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.open_correction.success_title'))
                    ->body(__('panel.actions.open_correction.success_body'))
                    ->send();
            });
    }

    /**
     * Rezumatul cererii — apare în TOATE modalele de decizie: cine e vizat, cine a depus, când.
     * (Feedback beneficiar: decizia se ia știind exact despre ce cerere e vorba.)
     */
    public static function requestSummary(DocumentRequest $record): string
    {
        return (string) __('panel.document_nav.request_summary', [
            'type' => $record->type->label(),
            'student' => (string) $record->student?->full_name,
            'requester' => (string) $record->requestedBy->name,
            'date' => (string) $record->created_at?->format('d.m.Y'),
        ]);
    }

    /**
     * Notele elevului pe care contestația le poate viza: active (neanulate), fără o corecție deja
     * în așteptare (invariantul „o singură propunere pe notă" trăiește în observer, dar nu oferim
     * opțiuni care ar pica garantat). Etichetă = disciplină — valoare (data), ca administrația să
     * identifice nota din descrierea liberă a familiei.
     *
     * @return array<int, string>
     */
    private static function contestableGradeOptions(DocumentRequest $record): array
    {
        return $record->student->grades()
            ->active()
            ->whereDoesntHave('corrections', fn (Builder $query) => $query->where('status', CorrectionStatus::Pending))
            ->with('subject')
            ->orderByDesc('graded_on')
            ->get()
            ->mapWithKeys(fn (Grade $grade): array => [
                $grade->id => sprintf(
                    '%s — %s (%s)',
                    $grade->subject->name,
                    $grade->value !== null ? rtrim(rtrim($grade->value, '0'), '.') : (string) $grade->calificativ,
                    $grade->graded_on->format('d.m.Y'),
                ),
            ])
            ->all();
    }

    /**
     * Disciplina notei selectate se notează numeric (1–10) sau prin calificativ? Comută câmpul de
     * valoare propusă din modal. Fără selecție încă → numeric (cazul dominant).
     */
    private static function selectedGradeIsNumeric(mixed $gradeId): bool
    {
        if ($gradeId === null || $gradeId === '') {
            return true;
        }

        $grade = Grade::query()->with('subject')->find((int) $gradeId);

        return $grade === null || $grade->subject->grading_type === GradingType::Numeric;
    }
}
