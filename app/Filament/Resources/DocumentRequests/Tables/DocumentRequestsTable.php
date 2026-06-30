<?php

namespace App\Filament\Resources\DocumentRequests\Tables;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
}
