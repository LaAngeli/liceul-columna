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

        $subject = (string) $message->subject;

        $recipient->notify(new CatalogNotification(
            NotificationType::NewMessage,
            'Mesaj nou de la '.($message->sender->name ?? 'expeditor'),
            $subject !== '' ? $subject : 'Ai primit un mesaj nou.',
            route('cabinet.messages', [], false),
        ));
    }
}
