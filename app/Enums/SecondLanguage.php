<?php

namespace App\Enums;

/**
 * A doua limbă străină studiată (legacy bdn_elevi.str_2).
 */
enum SecondLanguage: string
{
    case French = 'fr';
    case German = 'gm';
    case None = 'nu';

    public function label(): string
    {
        return match ($this) {
            self::French => 'Franceză',
            self::German => 'Germană',
            self::None => 'Fără',
        };
    }
}
