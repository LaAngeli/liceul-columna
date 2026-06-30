<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.messages.heading'))
            ->emptyStateDescription(__('panel.empty.messages.description'))
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->defaultSort('created_at', 'desc')
            ->poll('60s') // inbox — la 60s ca să nu fie agresiv pe firele lungi.
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->getStateUsing(fn (Message $record): bool => $record->read_at !== null),
                TextColumn::make('sender.name')
                    ->label(__('panel.tables.messages.from'))
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('panel.tables.messages.subject'))
                    ->limit(45)
                    ->placeholder(__('panel.common.dash')),
                TextColumn::make('type')
                    ->label(__('panel.fields.type'))
                    ->badge(),
                TextColumn::make('audience_domain')
                    ->label(__('panel.tables.messages.domain'))
                    ->badge()
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('panel.fields.received_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('panel.fields.type'))
                    ->options(MessageType::class),
                TernaryFilter::make('read')
                    ->label(__('panel.tables.messages.read_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.messages.read_yes'))
                    ->falseLabel(__('panel.tables.messages.read_no'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('read_at'),
                        false: fn (Builder $q) => $q->whereNull('read_at'),
                    ),
                SelectFilter::make('audience_domain')
                    ->label(__('panel.tables.messages.domain'))
                    ->options(AudienceDomain::class),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label(__('panel.actions.reply.label'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Message $record): bool => in_array(
                        auth()->id(),
                        [$record->sender_user_id, $record->recipient_user_id],
                        true,
                    ))
                    ->modalHeading(fn (): string => __('panel.actions.reply.heading'))
                    ->modalDescription(fn (Message $record): string => $record->body)
                    ->schema([
                        Textarea::make('body')
                            ->label(__('panel.actions.reply.body'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(function (Message $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(SendMessage::class)->reply($user, $record, $data['body']);

                        if ($record->recipient_user_id === $user->id) {
                            $record->markRead();
                        }

                        Notification::make()->success()->title(__('panel.actions.reply.success'))->send();
                    }),
                Action::make('markRead')
                    ->label(__('panel.actions.mark_read.label'))
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (Message $record): bool => $record->recipient_user_id === auth()->id()
                        && $record->read_at === null)
                    ->action(fn (Message $record) => $record->markRead()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markReadSelected')
                        ->label(__('panel.actions.mark_read_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            self::markReadBulk($records);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Marchează în masă ca citite doar mesajele primite (destinatar = userul curent) care încă
     * sunt necitite. Nu afectează mesajele trimise de el și nici pe cele deja citite.
     *
     * @param  Collection<int, Message>  $records
     */
    private static function markReadBulk(Collection $records): void
    {
        $userId = (int) auth()->id();
        $count = 0;

        foreach ($records as $record) {
            if ($record->recipient_user_id === $userId && $record->read_at === null) {
                $record->markRead();
                $count++;
            }
        }

        Notification::make()->success()->title(__('panel.actions.mark_read_bulk.success_count', ['count' => $count]))->send();
    }
}
