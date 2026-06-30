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
                    ->label(__('panel.forms.announcement.title'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('published_at')
                    ->label(__('panel.forms.announcement.published_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('panel.forms.announcement.draft'))
                    ->sortable(),
                TextColumn::make('read')
                    ->label(__('panel.forms.announcement.read'))
                    ->state(fn (Announcement $record): string => $record->isPublished()
                        ? $record->readCount().' / '.$record->recipients_count
                        : '—'),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('panel.forms.announcement.publish.label'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Announcement $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => __('panel.forms.announcement.publish.heading'))
                    ->modalDescription(fn (): string => __('panel.forms.announcement.publish.description'))
                    ->action(function (Announcement $record): void {
                        app(BroadcastAnnouncement::class)->publish($record);

                        Notification::make()->success()->title(__('panel.forms.announcement.publish.success'))->send();
                    }),
                EditAction::make()
                    ->visible(fn (Announcement $record): bool => ! $record->isPublished()),
            ]);
    }
}
