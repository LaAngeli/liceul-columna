<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul evaluării unei note (§2.1 / §2.4 din specificație). Tipul e obligatoriu și
 * predefinit; teza (ESS) NU se amestecă cu notele curente la calculul mediei.
 */
enum EvaluationType: string implements HasLabel
{
    case Curenta = 'curenta';
    case Esi = 'esi';
    case Teza = 'teza';

    public function label(): string
    {
        return match ($this) {
            self::Curenta => 'Curentă',
            self::Esi => 'ESI (sumativă intrasemestrială)',
            self::Teza => 'Teză (ESS)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Contribuie la media notelor curente (MC)? Teza e ponderată separat (50%).
     */
    public function countsAsCurrent(): bool
    {
        return $this !== self::Teza;
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
