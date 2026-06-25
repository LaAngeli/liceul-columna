<?php

namespace App\Enums;

/**
 * Modul de notare al unei discipline (legacy bdn_disc.notare).
 */
enum GradingType: string
{
    case Numeric = 'n';                 // notă numerică (1–10)
    case Calificativ = 'c';             // calificativ (FB/B/S/...)
    case CalificativDescriptiv = 'cd';  // calificativ + descriptor
    case Descriptiv = 'd';              // doar descriptiv

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Notă numerică',
            self::Calificativ => 'Calificativ',
            self::CalificativDescriptiv => 'Calificativ descriptiv',
            self::Descriptiv => 'Descriptiv',
        };
    }
}
