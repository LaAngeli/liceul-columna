<?php

namespace App\Filament\Resources\GradeCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class GradeCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.grade_corrections.heading'))
            ->emptyStateDescription(__('panel.empty.grade_corrections.description'))
            ->emptyStateIcon('heroicon-o-pencil-square')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de aprobare — aliniat cu badge-ul de sidebar.
            ->modifyQueryUsing(fn ($query) => $query->with('grade.student'))
            ->columns([
                TextColumn::make('grade.student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->url(fn (GradeCorrection $record): ?string => $record->grade?->student_id !== null
                        ? StudentResource::getUrl('edit', ['record' => $record->grade->student_id])
                        : null)
                    ->color('primary'),
                TextColumn::make('grade.subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state)),
                TextColumn::make('change')
                    ->label(__('panel.tables.grade_corrections.change'))
                    ->state(fn (GradeCorrection $record): string => trim(
                        ($record->old_value ?? $record->old_calificativ ?? '—')
                        .' → '
                        .($record->new_value ?? $record->new_calificativ ?? '—')
                    )),
                TextColumn::make('reason')
                    ->label(__('panel.fields.reason'))
                    ->wrap()
                    ->limit(50),
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (CorrectionStatus $state): string => $state->color()),
                TextColumn::make('requestedBy.name')
                    ->label(__('panel.fields.requested_by'))
                    ->toggleable(),
                TextColumn::make('reviewedBy.name')
                    ->label(__('panel.fields.reviewed_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(CorrectionStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('panel.actions.approve.label'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (GradeCorrection $record): bool => $record->isPending()
                        && (auth()->user()?->canApproveGradeCorrections() ?? false))
                    ->modalHeading(fn (): string => __('panel.actions.approve.label'))
                    ->modalDescription(fn (): string => __('panel.actions.approve_bulk.description'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.review_note'))
                            ->maxLength(255),
                    ])
                    ->action(function (GradeCorrection $record, array $data): void {
                        $record->approve((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->success()->title(__('panel.actions.approve.success'))->send();
                    }),
                Action::make('reject')
                    ->label(__('panel.actions.reject.label'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (GradeCorrection $record): bool => $record->isPending()
                        && (auth()->user()?->canApproveGradeCorrections() ?? false))
                    ->modalHeading(fn (): string => __('panel.actions.reject.label'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.rejection_reason'))
                            ->maxLength(255),
                    ])
                    ->action(function (GradeCorrection $record, array $data): void {
                        $record->reject((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title(__('panel.actions.reject.success'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label(__('panel.actions.approve_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.approve_bulk.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.approve_bulk.description'))
                        ->schema([
                            Textarea::make('review_note')
                                ->label(__('panel.common.review_note'))
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::reviewBulk($records, $data['review_note'] ?? null, approve: true);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rejectSelected')
                        ->label(__('panel.actions.reject_bulk.label'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.reject_bulk.heading'))
                        ->schema([
                            Textarea::make('review_note')
                                ->label(__('panel.common.rejection_reason'))
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::reviewBulk($records, $data['review_note'] ?? null, approve: false);
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible(fn (): bool => auth()->user()?->canApproveGradeCorrections() ?? false),
            ]);
    }

    /**
     * Aplică în masă aprobarea/respingerea corecțiilor în așteptare din selecție și anunță câte.
     *
     * @param  Collection<int, GradeCorrection>  $records
     */
    private static function reviewBulk(Collection $records, ?string $note, bool $approve): void
    {
        $userId = (int) auth()->id();
        $count = 0;

        foreach ($records as $record) {
            if (! $record->isPending()) {
                continue;
            }

            $approve ? $record->approve($userId, $note) : $record->reject($userId, $note);
            $count++;
        }

        Notification::make()
            ->{$approve ? 'success' : 'warning'}()
            ->title(__('panel.actions.'.($approve ? 'approve_bulk' : 'reject_bulk').'.success_count', ['count' => $count]))
            ->send();
    }
}
