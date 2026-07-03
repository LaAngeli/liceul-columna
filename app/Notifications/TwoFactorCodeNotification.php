<?php

namespace App\Notifications;

use App\Actions\SendTwoFactorEmailCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Codul 2FA pe email. FĂRĂ ShouldQueue — se trimite sincron (e în fluxul de login; un worker
 * oprit ar bloca autentificarea). Șablon pe limba destinatarului (User::notificationLocale()),
 * fără PII în corp — doar codul și valabilitatea.
 */
class TwoFactorCodeNotification extends Notification
{
    // `mailLocale`, nu `locale`: clasa de bază Notification are deja proprietatea $locale
    // (non-readonly) — redeclararea ei readonly e eroare fatală PHP.
    public function __construct(
        private readonly string $code,
        private readonly string $mailLocale = 'ro',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) trans('notifications.two_factor.subject', [], $this->mailLocale))
            ->greeting('Liceul Columna')
            ->line((string) trans('notifications.two_factor.intro', [], $this->mailLocale))
            ->line('## '.$this->code)
            ->line((string) trans('notifications.two_factor.expiry', [
                'minutes' => SendTwoFactorEmailCode::TTL_MINUTES,
            ], $this->mailLocale));
    }
}
