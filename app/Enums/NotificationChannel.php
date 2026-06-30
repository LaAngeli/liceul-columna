<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Canalele pe care poate sosi o notificare (spec §5; fără SMS). „Cabinet" = in-app (mereu
 * disponibil); restul cer un contact setat de familie + (pentru cele sociale) integrarea
 * configurată de liceu.
 */
enum NotificationChannel: string implements HasLabel
{
    case Cabinet = 'cabinet';
    case Email = 'email';
    case Telegram = 'telegram';
    case Viber = 'viber';

    public function label(): string
    {
        return (string) trans('notifications.channels.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Canalele care necesită un contact setat de utilizator (toate, mai puțin cabinetul).
     */
    public function requiresContact(): bool
    {
        return $this !== self::Cabinet;
    }

    /**
     * Canalele sociale (au nevoie și de integrarea liceului, nu doar de contactul familiei).
     *
     * @return list<self>
     */
    public static function social(): array
    {
        return [self::Telegram, self::Viber];
    }

    /**
     * Canalul ARE driver de livrare în aplicație? Toate canalele rămase (cabinet/email/telegram/viber)
     * au driver — sursa unică ce reflectă maparea din {@see CatalogNotification::via()}.
     */
    public function isDeliverable(): bool
    {
        return true;
    }

    /**
     * Canalul are credențialele de integrare setate de liceu? Cabinet/email mereu „da";
     * Telegram/Viber depind de token-ul din `.env` (config/services.php). Folosit pentru marcajul
     * „neconfigurat" în UI și pentru defense-in-depth în {@see CatalogNotification::via()}.
     */
    public function isConfigured(): bool
    {
        return match ($this) {
            self::Cabinet, self::Email => true,
            self::Telegram => filled(config('services.telegram.token')),
            self::Viber => filled(config('services.viber.token')),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Doar canalele LIVRABILE (au driver) — folosite în matricea de preferințe ca să nu oferim
     * canale pe care backendul nu le onorează niciodată. Spre deosebire de {@see options()} care
     * include toate cazurile (audit/etichetare completă).
     *
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            if ($case->isDeliverable()) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }

    /**
     * Starea de configurare a fiecărui canal (cheie = valoarea enum-ului). Folosită de UI ca să
     * marcheze vizual canalele sociale neactivate de liceu (badge „neconfigurat").
     *
     * @return array<string, bool>
     */
    public static function configurationStatus(): array
    {
        $status = [];
        foreach (self::cases() as $case) {
            $status[$case->value] = $case->isConfigured();
        }

        return $status;
    }
}
