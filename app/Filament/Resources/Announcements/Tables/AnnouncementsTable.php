<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Actions\BroadcastAnnouncement;
use App\Models\Announcement;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('published_at')
                    ->label('Publicat')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Ciornă')
                    ->sortable(),
                TextColumn::make('read')
                    ->label('Citit')
                    ->state(fn (Announcement $record): string => $record->isPublished()
                        ? $record->readCount().' / '.$record->recipients_count
                        : '—'),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publică')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Announcement $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->modalHeading('Publică anunțul')
                    ->modalDescription('Anunțul va fi trimis tuturor familiilor (inbox + email/social, după preferințe). Acțiune ireversibilă.')
                    ->action(function (Announcement $record): void {
                        app(BroadcastAnnouncement::class)->publish($record);

                        Notification::make()->success()->title('Anunț publicat')->send();
                    }),
                EditAction::make()
                    ->visible(fn (Announcement $record): bool => ! $record->isPublished()),
            ]);
    }
}
