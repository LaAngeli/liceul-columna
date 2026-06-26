<?php

namespace App\Filament\Resources\DocumentRequests\Tables;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (DocumentRequestType $state): string => $state->label()),
                TextColumn::make('student.full_name')
                    ->label('Elev')
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('requestedBy.name')
                    ->label('Solicitată de')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(DocumentRequestType::class),
                SelectFilter::make('status')
                    ->label('Stare')
                    ->options(RequestStatus::class),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (DocumentRequest $record): bool => $record->pdf_path !== null)
                    ->url(
                        fn (DocumentRequest $record): string => route('cabinet.requests.pdf', $record),
                        shouldOpenInNewTab: true,
                    ),
                Action::make('process')
                    ->label('Marchează procesată')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (DocumentRequest $record): bool => $record->status === RequestStatus::Pending)
                    ->action(function (DocumentRequest $record): void {
                        $record->markProcessed((int) auth()->id());

                        Notification::make()->success()->title('Cerere procesată')->send();
                    }),
            ]);
    }
}
