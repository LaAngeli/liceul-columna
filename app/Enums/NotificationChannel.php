<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Canalele pe care poate sosi o notificare (spec §5; fără SMS). „Cabinet" = in-app (mereu
 * disponibil); restul cer un contact setat de familie + (pentru cele sociale) integrarea
 * configurată de liceu. WhatsApp e listat, dar trimiterea efectivă e amânată (API plătit).
 */
enum NotificationChannel: string implements HasLabel
{
    case Cabinet = 'cabinet';
    case Email = 'email';
    case Telegram = 'telegram';
    case Viber = 'viber';
    case Messenger = 'messenger';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Cabinet => 'Cabinet (în aplicație)',
            self::Email => 'E-mail',
            self::Telegram => 'Telegram',
            self::Viber => 'Viber',
            self::Messenger => 'Messenger',
            self::Whatsapp => 'WhatsApp',
        };
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
        return [self::Telegram, self::Viber, self::Messenger, self::Whatsapp];
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
}
