<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageMailbox;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Lista poștei personalului: fiecare rând e o CONVERSAȚIE (firul-rădăcină), nu un mesaj individual.
 *
 * Baza vine din `MessageResource::getEloquentQuery()` (fire-rădăcină la care particip); folderul
 * activ e aplicat de tab prin {@see MessageMailbox::applyFolder()} — aceeași definiție ca la cabinet.
 */
class MessagesTable
{
    public static function configure(Table $table): Table
    {
        $uid = (int) auth()->id();

        return $table
            ->emptyStateHeading(__('panel.empty.messages.heading'))
            ->emptyStateDescription(__('panel.empty.messages.description'))
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->poll('30s')
            ->defaultSort('last_activity_at', 'desc')
            // Rândul întreg duce în conversație (tipar de client de poștă).
            ->recordUrl(fn (Message $record): string => MessageResource::getUrl('thread', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => self::hydrate($query, $uid))
            ->columns([
                // Plic închis/deschis — semnalul cel mai rapid de scanat.
                IconColumn::make('unread')
                    ->label('')
                    ->icon(fn (Message $record): string => self::unreadCount($record, $uid) > 0
                        ? 'heroicon-s-envelope'
                        : 'heroicon-o-envelope-open')
                    ->color(fn (Message $record): string => self::unreadCount($record, $uid) > 0 ? 'info' : 'gray'),

                // Stea — comutabilă direct din listă, stare PER-UTILIZATOR.
                IconColumn::make('starred')
                    ->label('')
                    ->icon(fn (Message $record): string => self::isStarred($record) ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (Message $record): string => self::isStarred($record) ? 'warning' : 'gray')
                    ->tooltip(fn (Message $record): string => self::isStarred($record)
                        ? __('panel.mailbox.unstar')
                        : __('panel.mailbox.star'))
                    ->action(function (Message $record): void {
                        $user = self::currentUser();

                        if (! $user instanceof User) {
                            return;
                        }

                        $state = MessageMailbox::for($user)->stateForThread($record);
                        $state->starred_at = $state->starred_at === null ? now() : null;
                        $state->save();
                    }),

                TextColumn::make('with')
                    ->label(__('panel.mailbox.with'))
                    ->getStateUsing(fn (Message $record): string => self::counterpartName($record, $uid))
                    ->weight(fn (Message $record): ?string => self::unreadCount($record, $uid) > 0 ? 'bold' : null),

                // Subiect (bold la necitit) + fragmentul ultimului mesaj, ca la un client de poștă.
                TextColumn::make('subject')
                    ->label(__('panel.tables.messages.subject'))
                    ->placeholder(__('panel.common.dash'))
                    ->limit(46)
                    ->weight(fn (Message $record): ?string => self::unreadCount($record, $uid) > 0 ? 'bold' : null)
                    ->description(fn (Message $record): ?string => self::snippet($record))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('panel.fields.type'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),

                IconColumn::make('attachments_count')
                    ->label('')
                    ->boolean()
                    ->getStateUsing(fn (Message $record): bool => (int) ($record->attachments_count ?? 0) > 0)
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('gray')
                    ->falseColor('gray')
                    ->toggleable(),

                TextColumn::make('last_activity_at')
                    ->label(__('panel.mailbox.last_activity'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('panel.fields.type'))
                    ->options(MessageType::class),
                SelectFilter::make('audience_domain')
                    ->label(__('panel.tables.messages.domain'))
                    ->options(AudienceDomain::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markReadSelected')
                        ->label(__('panel.actions.mark_read_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('gray')
                        ->action(fn (Collection $records) => self::markReadBulk($records))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('trashSelected')
                        ->label(__('panel.mailbox.trash_bulk'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        // Altfel butonul modalului rămâne „Executați" (implicitul Filament).
                        ->modalSubmitActionLabel(__('panel.mailbox.trash'))
                        ->action(fn (Collection $records) => self::trashBulk($records))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Eager-load scopat + agregatele listei, ca fiecare rând să nu mai interogheze separat:
     *  • `states` DOAR ale utilizatorului curent (stea/coș) — altfel N+1 la fiecare render;
     *  • numărul de răspunsuri necitite de mine + de atașamente;
     *  • `last_activity_at` = ultimul mesaj din fir (răspuns sau, în lipsă, rădăcina).
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    private static function hydrate(Builder $query, int $uid): Builder
    {
        return $query
            ->with([
                'sender:id,name',
                'recipient:id,name',
                'student',
                'states' => fn ($relation) => $relation->where('user_id', $uid),
            ])
            ->withCount([
                'attachments',
                'replies as unread_replies_count' => fn (Builder $reply) => $reply
                    ->where('recipient_user_id', $uid)
                    ->whereNull('read_at'),
            ])
            ->selectRaw(
                'messages.*, COALESCE((SELECT MAX(r.created_at) FROM messages r WHERE r.parent_id = messages.id AND r.deleted_at IS NULL), messages.created_at) AS last_activity_at'
            );
    }

    /** Necitite de MINE în fir: rădăcina (dacă mi-e adresată) + răspunsurile neatinse. */
    private static function unreadCount(Message $record, int $uid): int
    {
        $root = ((int) $record->recipient_user_id === $uid && $record->read_at === null) ? 1 : 0;

        return $root + (int) ($record->unread_replies_count ?? 0);
    }

    private static function isStarred(Message $record): bool
    {
        return $record->states->first()?->starred_at !== null;
    }

    private static function counterpartName(Message $record, int $uid): string
    {
        return (int) $record->sender_user_id === $uid
            ? (string) $record->recipient?->name
            : (string) $record->sender?->name;
    }

    private static function snippet(Message $record): ?string
    {
        $body = (string) preg_replace('/\s+/', ' ', $record->body);

        return $body === '' ? null : Str::limit($body, 78);
    }

    private static function currentUser(): ?User
    {
        $user = auth('web')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Marchează citite conversațiile selectate — doar mesajele PRIMITE de utilizator.
     * Rândurile vin deja din query-ul scopat pe participant; re-verificăm oricum (anti-IDOR).
     *
     * @param  Collection<int, Message>  $records
     */
    private static function markReadBulk(Collection $records): void
    {
        $user = self::currentUser();

        if (! $user instanceof User) {
            return;
        }

        $mailbox = MessageMailbox::for($user);
        $count = 0;

        foreach ($records as $record) {
            if (! $mailbox->participatesIn($record)) {
                continue;
            }

            $mailbox->markThreadRead($record);
            $count++;
        }

        Notification::make()->success()->title(__('panel.actions.mark_read_bulk.success_count', ['count' => $count]))->send();
    }

    /**
     * Mută conversațiile selectate în coșul PROPRIU (nu afectează cutia celuilalt participant).
     *
     * @param  Collection<int, Message>  $records
     */
    private static function trashBulk(Collection $records): void
    {
        $user = self::currentUser();

        if (! $user instanceof User) {
            return;
        }

        $mailbox = MessageMailbox::for($user);
        $count = 0;

        foreach ($records as $record) {
            if (! $mailbox->participatesIn($record)) {
                continue;
            }

            $state = $mailbox->stateForThread($record);
            $state->trashed_at = now();
            $state->save();
            $count++;
        }

        Notification::make()->success()->title(__('panel.mailbox.trash_bulk_success', ['count' => $count]))->send();
    }
}
