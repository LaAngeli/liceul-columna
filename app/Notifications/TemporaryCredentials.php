<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Datele de autentificare trimise pe e-mail la crearea/resetarea unui cont din panou
 * (opțiunea „Trimite datele de autentificare"). Conține o parolă TEMPORARĂ — utilizatorul
 * e obligat să o schimbe la prima autentificare (must_change_password), deci fereastra de
 * expunere e minimă. Pe queue, ca orice notificare (principiile proiectului §5).
 */
class TemporaryCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User
            ? ($notifiable->notification_locale ?? $notifiable->locale ?? config('app.locale'))
            : config('app.locale');

        $identifier = $notifiable instanceof User
            ? ($notifiable->username ?? $notifiable->email)
            : null;

        return (new MailMessage)
            ->subject(__('panel.credentials_mail.subject', [], $locale))
            ->greeting(__('panel.credentials_mail.greeting', ['name' => $notifiable->name ?? ''], $locale))
            ->line(__('panel.credentials_mail.intro', [], $locale))
            ->line(__('panel.credentials_mail.username', ['username' => $identifier], $locale))
            ->line(__('panel.credentials_mail.password', ['password' => $this->temporaryPassword], $locale))
            ->line(__('panel.credentials_mail.must_change', [], $locale))
            ->action(__('panel.credentials_mail.action', [], $locale), route('login'));
    }
}
