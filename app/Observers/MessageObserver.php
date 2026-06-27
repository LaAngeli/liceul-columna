<?php

namespace App\Observers;

use App\Enums\NotificationType;
use App\Models\Message;
use App\Models\User;
use App\Notifications\CatalogNotification;

/**
 * Notifică destinatarul la un mesaj NOU (spec §5).
 */
class MessageObserver
{
    public function created(Message $message): void
    {
        $recipient = User::query()->find($message->recipient_user_id);

        if ($recipient === null) {
            return;
        }

        $recipient->notify(new CatalogNotification(
            NotificationType::NewMessage,
            ['sender' => $message->sender->name ?? 'expeditor'],
            route('cabinet.messages', [], false),
        ));
    }
}
