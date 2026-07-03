<?php

namespace App\Actions;

use App\Models\TwoFactorEmailCode;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Generează și trimite un cod OTP pe email (6 cifre, TTL limitat, un singur cod activ per
 * utilizator). Trimiterea e SINCRONĂ (fluxul de login nu poate aștepta un worker de coadă).
 * `$pendingEmail` = adresa nouă în curs de verificare la activare (conturile fără email).
 */
class SendTwoFactorEmailCode
{
    /** Interval minim între două trimiteri (anti-spam, pe lângă rate-limiter). */
    public const COOLDOWN_SECONDS = 60;

    /** Valabilitatea codului, în minute. */
    public const TTL_MINUTES = 10;

    /**
     * @return bool false = destinatar lipsă sau încă în cooldown (nu s-a trimis nimic)
     */
    public function execute(User $user, ?string $pendingEmail = null): bool
    {
        $recipient = $pendingEmail ?? $user->email;

        if ($recipient === null || $recipient === '') {
            return false;
        }

        $existing = TwoFactorEmailCode::query()->where('user_id', $user->id)->first();

        if ($existing !== null && $existing->sent_at->gt(now()->subSeconds(self::COOLDOWN_SECONDS))) {
            return false;
        }

        $code = (string) random_int(100000, 999999);

        TwoFactorEmailCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => hash('sha256', $code),
                'pending_email' => $pendingEmail,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
                'attempts' => 0,
            ],
        );

        // On-demand (route), nu $user->notify(): destinatarul poate fi adresa ÎN CURS de
        // verificare, diferită de emailul curent al contului.
        Notification::route('mail', $recipient)
            ->notify(new TwoFactorCodeNotification($code, $user->notificationLocale()));

        return true;
    }
}
