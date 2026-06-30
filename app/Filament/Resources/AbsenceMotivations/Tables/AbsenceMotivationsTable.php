<?php

namespace App\Filament\Resources\AbsenceMotivations\Tables;

use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AbsenceMotivationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.absence_motivations.heading'))
            ->emptyStateDescription(__('panel.empty.absence_motivations.description'))
            ->emptyStateIcon('heroicon-o-check-badge')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de aprobare — aliniat cu badge-ul de sidebar.
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('period')
                    ->label(__('panel.fields.period'))
                    ->state(fn (AbsenceMotivation $record): string => $record->period_start->format('d.m.Y').' – '.$record->period_end->format('d.m.Y')),
                TextColumn::make('reason')
                    ->label(__('panel.fields.reason'))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color()),
                TextColumn::make('is_exception')
                    ->label(__('panel.fields.type'))
                    ->badge()
                    ->state(fn (AbsenceMotivation $record): string => $record->is_exception
                        ? __('panel.tables.absence_motivations.type_exception')
                        : __('panel.tables.absence_motivations.type_normal'))
                    ->color(fn (AbsenceMotivation $record): string => $record->is_exception ? 'warning' : 'gray'),
                TextColumn::make('validation_deadline')
                    ->label(__('panel.tables.absence_motivations.validation_deadline'))
                    ->state(fn (AbsenceMotivation $record): string => $record->isPending()
                        ? ($record->validationDeadline()?->format('d.m.Y') ?? '—')
                        : '—')
                    ->badge()
                    ->color(fn (AbsenceMotivation $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->tooltip(fn (AbsenceMotivation $record): ?string => $record->isOverdue()
                        ? (string) __('panel.tables.absence_motivations.overdue_tooltip')
                        : null),
                TextColumn::make('requestedBy.name')
                    ->label(__('panel.fields.requested_by'))
                    ->toggleable(),
                TextColumn::make('reviewedBy.name')
                    ->label(__('panel.fields.validated_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('panel.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(RequestStatus::class),
            ])
            ->recordActions([
                Action::make('document')
                    ->label(__('panel.actions.document.label'))
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (AbsenceMotivation $record): bool => $record->document_path !== null)
                    ->url(fn (AbsenceMotivation $record): string => route('cabinet.motivation.document', $record), shouldOpenInNewTab: true),
                Action::make('approve')
                    ->label(__('panel.actions.validate.label'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AbsenceMotivation $record): bool => self::canReview($record))
                    ->modalHeading(fn (): string => __('panel.actions.validate.label'))
                    ->modalDescription(fn (): string => __('panel.actions.validate_bulk.description'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.review_note'))
                            ->maxLength(255),
                    ])
                    ->action(function (AbsenceMotivation $record, array $data): void {
                        $record->approve((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->success()->title(__('panel.actions.validate.success'))->send();
                    }),
                Action::make('reject')
                    ->label(__('panel.actions.reject.label'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AbsenceMotivation $record): bool => self::canReview($record))
                    ->modalHeading(fn (): string => __('panel.actions.reject.label'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.rejection_reason'))
                            ->maxLength(255),
                    ])
                    ->action(function (AbsenceMotivation $record, array $data): void {
                        $record->reject((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title(__('panel.actions.reject.success'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label(__('panel.actions.validate_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.validate_bulk.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.validate_bulk.description'))
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
                        ->label(__('panel.actions.reject_motivations_bulk.label'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.reject_motivations_bulk.heading'))
                        ->schema([
                            Textarea::make('review_note')
                                ->label(__('panel.common.rejection_reason'))
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::reviewBulk($records, $data['review_note'] ?? null, approve: false);
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible(fn (): bool => ($user = auth()->user()) instanceof User
                    && ($user->isManagement() || $user->teacher !== null)),
            ]);
    }

    /**
     * Poate utilizatorul curent să valideze/respingă cererea (diriginte pentru normale,
     * vicedirector pe educație pentru excepții) — vezi {@see AbsenceMotivation::canBeReviewedBy()}.
     */
    private static function canReview(AbsenceMotivation $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $record->canBeReviewedBy($user);
    }

    /**
     * Aplică în masă validarea/respingerea, dar DOAR pe cererile în așteptare pe care
     * utilizatorul curent are dreptul să le revizuiască (per-rând {@see canReview()}).
     *
     * @param  Collection<int, AbsenceMotivation>  $records
     */
    private static function reviewBulk(Collection $records, ?string $note, bool $approve): void
    {
        $userId = (int) auth()->id();
        $count = 0;

        foreach ($records as $record) {
            if (! $record->isPending() || ! self::canReview($record)) {
                continue;
            }

            $approve ? $record->approve($userId, $note) : $record->reject($userId, $note);
            $count++;
        }

        Notification::make()
            ->{$approve ? 'success' : 'warning'}()
            ->title(__('panel.actions.'.($approve ? 'validate_bulk' : 'reject_motivations_bulk').'.success_count', ['count' => $count]))
            ->send();
    }
}
