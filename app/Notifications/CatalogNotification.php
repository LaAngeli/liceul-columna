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
 * Notificare unică, parametrizată pe tip (spec §5). Textul NU e fixat la creare: e randat din
 * șabloanele predefinite `lang/{ro,ru,en}/notifications.php`, în limba ALEASĂ de fiecare
 * destinatar ({@see User::notificationLocale()}) — fără traducere în timp real. `via()` citește
 * preferințele de canal pentru acel tip și livrează doar pe canalele alese + configurate.
 *
 * Pentru anunțuri (text liber, scris de conducere) se pot trece `customTitle`/`customBody`, care
 * ocolesc șablonul. Pe queue (sincron în dev, async cu Horizon în Faza B).
 */
class CatalogNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $params  Valorile placeholderelor din șablon (`:student`...).
     */
    public function __construct(
        public NotificationType $type,
        public array $params = [],
        public ?string $url = null,
        public ?string $customTitle = null,
        public ?string $customBody = null,
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
     * Randează titlul + corpul în limba de notificare a destinatarului (sau textul liber, dacă a
     * fost dat la creare).
     *
     * @return array{title: string, body: string}
     */
    protected function rendered(User $notifiable): array
    {
        if ($this->customTitle !== null) {
            return ['title' => $this->customTitle, 'body' => $this->customBody ?? ''];
        }

        $locale = $notifiable->notificationLocale();

        return [
            'title' => (string) trans("notifications.{$this->type->value}.title", $this->params, $locale),
            'body' => (string) trans("notifications.{$this->type->value}.body", $this->params, $locale),
        ];
    }

    /**
     * Canalul „database": inboxul din cabinet (familie) ȘI clopoțelul Filament (personal). Filament
     * citește title/body/icon; cabinetul citește type/title/body/url — formatul le acoperă pe ambele.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $rendered = $this->rendered($notifiable);

        return [
            'type' => $this->type->value,
            'title' => $rendered['title'],
            'body' => $rendered['body'],
            'url' => $this->url,
            'icon' => $this->type->icon(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $rendered = $this->rendered($notifiable);

        $mail = (new MailMessage)
            ->subject($rendered['title'])
            ->greeting('Liceul Columna')
            ->line($rendered['body']);

        if ($this->url !== null) {
            $label = (string) trans('notifications.open', [], $notifiable->notificationLocale());
            $mail->action($label, url($this->url));
        }

        return $mail;
    }

    /**
     * Textul pentru canalele sociale (Telegram/Viber/Messenger), în limba destinatarului.
     */
    public function toSocialText(User $notifiable): string
    {
        $rendered = $this->rendered($notifiable);

        return $rendered['body'] === ''
            ? $rendered['title']
            : $rendered['title']."\n".$rendered['body'];
    }
}
