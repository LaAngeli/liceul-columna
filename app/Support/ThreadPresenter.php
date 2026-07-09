<?php

namespace App\Support;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Prezintă un fir (rădăcină + răspunsuri) din perspectiva unui utilizator, pentru cabinetul
 * Inertia. Panoul staff (Filament) randează firul în Blade, deci NU trece pe aici — dar
 * ambele citesc aceleași rânduri prin {@see MessageMailbox}.
 */
final class ThreadPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Message $root, int $userId): array
    {
        /** @var Collection<int, Message> $all */
        $all = collect([$root])->merge($root->replies)->sortBy('created_at')->values();
        $last = $all->last();
        $mineRoot = (int) $root->sender_user_id === $userId;

        $attachmentCount = $all->sum(fn (Message $message): int => $message->attachments->count());

        return [
            'id' => $root->id,
            'subject' => $root->subject ?? $root->type->label(),
            'type' => $root->type->value,
            'student' => $root->student?->full_name,
            'studentId' => $root->student_id,
            // Id-ul CELUILALT participant — cheia de mapare fir ↔ persoană din roster.
            'withId' => $mineRoot ? (int) $root->recipient_user_id : (int) $root->sender_user_id,
            'with' => $mineRoot ? $root->recipient->name : $root->sender->name,
            'direction' => $mineRoot ? 'sent' : 'received',
            'starred' => $root->states->first()?->starred_at !== null,
            'trashed' => $root->states->first()?->trashed_at !== null,
            'snippet' => $last !== null ? Str::limit((string) preg_replace('/\s+/', ' ', $last->body), 110) : '',
            'lastAt' => $last?->created_at?->format('d.m.Y H:i'),
            'unread' => $all->where('recipient_user_id', $userId)->whereNull('read_at')->count(),
            'attachmentCount' => $attachmentCount,
            'messages' => $all->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => (int) $message->sender_user_id === $userId,
                'senderName' => $message->sender->name,
                'at' => $message->created_at?->format('d.m.Y H:i'),
                'attachments' => $message->attachments->map(fn (MessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->humanSize(),
                    'isImage' => $attachment->isImage(),
                    'url' => route('cabinet.messages.attachment', $attachment),
                ])->all(),
            ])->all(),
        ];
    }
}
