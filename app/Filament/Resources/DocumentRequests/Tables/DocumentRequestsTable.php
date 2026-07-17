<?php

namespace App\Filament\Resources\DocumentRequests\Tables;

use App\Enums\RequestStatus;
use App\Filament\Resources\DocumentRequests\DocumentRequestActions;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Models\DocumentRequest;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tabelul cozii de cereri — trăiește în contextul navigatorului (vedere + tip), deci coloanele
 * nu repetă contextul: fără coloană de tip, filtrul de stare doar în arhivă. Comentariul
 * familiei (detaliile) apare direct în rând — decizia se ia văzând CE cere familia.
 */
class DocumentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListDocumentRequests
                ? $livewire->applyRequestsContext($query)
                : $query)
            ->emptyStateHeading(__('panel.empty.document_requests.heading'))
            ->emptyStateDescription(__('panel.empty.document_requests.description'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de procesare — aliniat cu badge-ul de sidebar.
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.fields.received_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->weight('bold')
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('requestedBy.name')
                    ->label(__('panel.fields.requested_by'))
                    ->toggleable(),
                TextColumn::make('details')
                    ->label(__('panel.document_nav.family_details'))
                    ->state(fn (DocumentRequest $record): string => (string) ($record->payload['details'] ?? ''))
                    ->limit(60)
                    ->placeholder(__('panel.document_nav.no_details'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color()),
                TextColumn::make('reviewedBy.name')
                    ->label(__('panel.forms.admission.processed_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->description(fn (DocumentRequest $record): ?string => $record->reviewed_at?->format('d.m.Y H:i'))
                    ->visible(fn ($livewire): bool => $livewire instanceof ListDocumentRequests && $livewire->isArchiveView()),
                TextColumn::make('review_note')
                    ->label(__('panel.document_nav.review_note'))
                    ->limit(40)
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($livewire): bool => $livewire instanceof ListDocumentRequests && $livewire->isArchiveView()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options([
                        RequestStatus::Approved->value => RequestStatus::Approved->label(),
                        RequestStatus::Rejected->value => RequestStatus::Rejected->label(),
                    ])
                    // În coadă totul e „în așteptare" — filtrul are sens doar în arhivă.
                    ->visible(fn ($livewire): bool => $livewire instanceof ListDocumentRequests && $livewire->isArchiveView()),
            ])
            ->recordActions([
                ViewAction::make(),
                DocumentRequestActions::pdf(),
                DocumentRequestActions::openCorrection(),
                DocumentRequestActions::process(),
                DocumentRequestActions::reject(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('processSelected')
                        ->label(__('panel.actions.process_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.process_bulk.heading'))
                        ->visible(fn ($livewire): bool => ! ($livewire instanceof ListDocumentRequests) || ! $livewire->isArchiveView())
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
