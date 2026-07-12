<?php

namespace App\Filament\Resources\DocumentRequests\Tables;

use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\GradingType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DocumentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.document_requests.heading'))
            ->emptyStateDescription(__('panel.empty.document_requests.description'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de procesare — aliniat cu badge-ul de sidebar.
            ->columns([
                TextColumn::make('type')
                    ->label(__('panel.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentRequestType $state): string => $state->label()),
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('requestedBy.name')
                    ->label(__('panel.fields.requested_by'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('panel.fields.type'))
                    ->options(DocumentRequestType::class),
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(RequestStatus::class),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label(__('panel.actions.pdf.label'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (DocumentRequest $record): bool => $record->pdf_path !== null)
                    ->url(
                        fn (DocumentRequest $record): string => route('cabinet.requests.pdf', $record),
                        shouldOpenInNewTab: true,
                    ),
                Action::make('openCorrection')
                    ->label(__('panel.actions.open_correction.label'))
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->modalSubmitActionLabel(__('panel.actions.open_correction.submit'))
                    // Fluxul contestație→corecție (#36): contestația familiei NU rămâne un PDF mort —
                    // administrația o transformă într-o cerere formală de corecție (judecată apoi de
                    // aprobatorii din „Corecții note"), iar cererea e închisă cu trimitere la corecție.
                    ->visible(fn (DocumentRequest $record): bool => $record->type === DocumentRequestType::Contestatie
                        && $record->status === RequestStatus::Pending)
                    ->modalHeading(fn (): string => __('panel.actions.open_correction.heading'))
                    ->modalDescription(fn (): string => __('panel.actions.open_correction.description'))
                    ->schema([
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
                    }),
                Action::make('process')
                    ->label(__('panel.actions.process.label'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DocumentRequest $record): bool => $record->status === RequestStatus::Pending)
                    ->action(function (DocumentRequest $record): void {
                        $record->markProcessed((int) auth()->id());

                        Notification::make()->success()->title(__('panel.actions.process.success'))->send();
                    }),
                Action::make('reject')
                    ->label(__('panel.actions.reject_request.label'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (DocumentRequest $record): bool => $record->status === RequestStatus::Pending)
                    ->modalHeading(fn (): string => __('panel.actions.reject_request.heading'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.rejection_reason'))
                            ->maxLength(255),
                    ])
                    ->action(function (DocumentRequest $record, array $data): void {
                        $record->markRejected((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title(__('panel.actions.reject_request.success'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('processSelected')
                        ->label(__('panel.actions.process_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.process_bulk.heading'))
                        ->action(function (Collection $records): void {
                            self::processBulk($records);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Marchează în masă ca procesate doar cererile încă în așteptare din selecție.
     *
     * @param  Collection<int, DocumentRequest>  $records
     */
    private static function processBulk(Collection $records): void
    {
        $userId = (int) auth()->id();
        $count = 0;

        foreach ($records as $record) {
            if ($record->status !== RequestStatus::Pending) {
                continue;
            }

            $record->markProcessed($userId);
            $count++;
        }

        Notification::make()->success()->title(__('panel.actions.process_bulk.success_count', ['count' => $count]))->send();
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
