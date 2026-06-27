<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Zilele săptămânii pentru orarul structurat (luni–sâmbătă; duminica nu se predă). Valoarea = ISO
 * day-of-week (1 = luni), ca să se sorteze natural.
 */
enum Weekday: int implements HasLabel
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Luni',
            self::Tuesday => 'Marți',
            self::Wednesday => 'Miercuri',
            self::Thursday => 'Joi',
            self::Friday => 'Vineri',
            self::Saturday => 'Sâmbătă',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Monday => 'Lu',
            self::Tuesday => 'Ma',
            self::Wednesday => 'Mi',
            self::Thursday => 'Jo',
            self::Friday => 'Vi',
            self::Saturday => 'Sâ',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * @return array<int, string>
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
