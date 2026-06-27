<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trimite prin Facebook Messenger (Pagină + Page Access Token; gratuit). Activ doar dacă
 * `services.messenger.token` e setat ȘI utilizatorul are un PSID (obținut când a scris primul
 * Paginii). Folosește `MESSAGE_TAG: ACCOUNT_UPDATE` pentru notificări tranzacționale în afara
 * ferestrei de 24h. Sărit elegant dacă nu e configurat. Fără pachet Composer (HTTP direct).
 */
class MessengerChannel
{
    public function send(User $notifiable, CatalogNotification $notification): void
    {
        $token = config('services.messenger.token');
        $psid = $notifiable->notificationContact(NotificationChannel::Messenger);

        if (! is_string($token) || $token === '' || $psid === null) {
            return;
        }

        try {
            Http::asJson()->post("https://graph.facebook.com/v19.0/me/messages?access_token={$token}", [
                'recipient' => ['id' => $psid],
                'messaging_type' => 'MESSAGE_TAG',
                'tag' => 'ACCOUNT_UPDATE',
                'message' => ['text' => $notification->toSocialText($notifiable)],
            ]);
        } catch (Throwable $e) {
            Log::warning('Notificare Messenger eșuată: '.$e->getMessage());
        }
    }
}
