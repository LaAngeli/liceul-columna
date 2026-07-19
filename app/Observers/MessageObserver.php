<?php

namespace App\Observers;

use App\Enums\NotificationType;
use App\Enums\UserRole;
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

        // Ținta duce DIRECT PE FIR (`?fir=` = deep-link în ambele poște), în cutia potrivită
        // destinatarului: personalul își citește mesajele în panou, familia în cabinet — un link
        // spre /cabinet/mesaje era o fundătură pentru un profesor.
        $rootId = (int) ($message->parent_id ?? $message->id);
        $url = $recipient->hasAnyRole(UserRole::panelRoleValues())
            ? '/admin/mesaje?fir='.$rootId
            : route('cabinet.messages', ['fir' => $rootId], false);

        $recipient->notify(new CatalogNotification(
            NotificationType::NewMessage,
            ['sender' => $message->sender->name ?? 'expeditor'],
            $url,
        ));
    }
}
