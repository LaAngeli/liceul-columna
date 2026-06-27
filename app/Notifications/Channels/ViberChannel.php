<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trimite prin botul Viber (gratuit). Activ doar dacă `services.viber.token` e setat ȘI utilizatorul
 * are un receiver Viber. Altfel — sărit elegant. Fără pachet Composer (HTTP direct).
 */
class ViberChannel
{
    public function send(User $notifiable, CatalogNotification $notification): void
    {
        $token = config('services.viber.token');
        $receiver = $notifiable->notificationContact(NotificationChannel::Viber);

        if (! is_string($token) || $token === '' || $receiver === null) {
            return;
        }

        try {
            Http::withHeaders(['X-Viber-Auth-Token' => $token])->asJson()
                ->post('https://chatapi.viber.com/pa/send_message', [
                    'receiver' => $receiver,
                    'sender' => ['name' => (string) config('services.viber.sender', 'Liceul Columna')],
                    'type' => 'text',
                    'text' => $notification->toSocialText($notifiable),
                ]);
        } catch (Throwable $e) {
            Log::warning('Notificare Viber eșuată: '.$e->getMessage());
        }
    }
}
