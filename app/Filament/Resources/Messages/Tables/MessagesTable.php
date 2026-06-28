<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Actions\SendMessage;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->getStateUsing(fn (Message $record): bool => $record->read_at !== null),
                TextColumn::make('sender.name')
                    ->label('De la')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Subiect')
                    ->limit(45)
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                TextColumn::make('audience_domain')
                    ->label('Domeniu')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('student.full_name')
                    ->label('Elev')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Primit')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(MessageType::class),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Răspunde')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Message $record): bool => in_array(
                        auth()->id(),
                        [$record->sender_user_id, $record->recipient_user_id],
                        true,
                    ))
                    ->modalHeading('Răspunde')
                    ->modalDescription(fn (Message $record): string => $record->body)
                    ->schema([
                        Textarea::make('body')
                            ->label('Răspuns')
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

                        Notification::make()->success()->title('Răspuns trimis')->send();
                    }),
                Action::make('markRead')
                    ->label('Marchează citit')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (Message $record): bool => $record->recipient_user_id === auth()->id()
                        && $record->read_at === null)
                    ->action(fn (Message $record) => $record->markRead()),
            ]);
    }
}
