<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trimite prin botul Telegram (gratuit). Activ doar dacă `services.telegram.token` e setat în .env
 * ȘI utilizatorul are un chat_id. Altfel — sărit elegant. Fără pachet Composer (HTTP direct).
 */
class TelegramChannel
{
    public function send(User $notifiable, CatalogNotification $notification): void
    {
        $token = config('services.telegram.token');
        $chatId = $notifiable->notificationContact(NotificationChannel::Telegram);

        if (! is_string($token) || $token === '' || $chatId === null) {
            return;
        }

        try {
            Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $notification->toSocialText($notifiable),
            ]);
        } catch (Throwable $e) {
            Log::warning('Notificare Telegram eșuată: '.$e->getMessage());
        }
    }
}
