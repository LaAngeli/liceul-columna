<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\Channels\MessengerChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\ViberChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificare unică, parametrizată pe tip (spec §5). `via()` citește preferințele utilizatorului
 * pentru acel tip și livrează DOAR pe canalele alese + setate. Pe queue (sincron în dev, async cu
 * Horizon în Faza B). Canalele sociale neconfigurate / fără contact sunt sărite elegant.
 */
class CatalogNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NotificationType $type,
        public string $title,
        public string $body = '',
        public ?string $url = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $map = [
            NotificationChannel::Cabinet->value => 'database',
            NotificationChannel::Email->value => 'mail',
            NotificationChannel::Telegram->value => TelegramChannel::class,
            NotificationChannel::Viber->value => ViberChannel::class,
            NotificationChannel::Messenger->value => MessengerChannel::class,
            // WhatsApp: amânat (API plătit) — fără canal activ.
        ];

        $channels = [];
        foreach ($notifiable->channelsFor($this->type) as $channel) {
            if ($channel->requiresContact() && $notifiable->notificationContact($channel) === null) {
                continue;
            }
            if (isset($map[$channel->value])) {
                $channels[] = $map[$channel->value];
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * Canalul „database" (inboxul din cabinet).
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Liceul Columna')
            ->line($this->body);

        if ($this->url !== null) {
            $mail->action('Deschide în cabinet', url($this->url));
        }

        return $mail;
    }

    /**
     * Textul folosit de canalele sociale (Telegram/Viber/Messenger).
     */
    public function toSocialText(): string
    {
        return $this->body === '' ? $this->title : $this->title."\n".$this->body;
    }
}
