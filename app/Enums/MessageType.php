<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul unui mesaj în modulul de comunicare (spec §4):
 * - Direct: comunicare liberă spre nivelul firesc (familie ↔ profesor/diriginte);
 * - Audience: „Solicitare audiență / sesizare" — rutată spre prim-vicedirector (familia NU scrie
 *   direct conducerii, §4.2), escaladabilă spre director.
 *
 * Fluxurile avansate (mesaj comportamental filtrat, anunț broadcast cu confirmare de citire)
 * se adaugă ulterior — vezi §4.2 / §5.
 */
enum MessageType: string implements HasLabel
{
    case Direct = 'direct';
    case Audience = 'audience';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Mesaj',
            self::Audience => 'Solicitare audiență',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Direct => 'primary',
            self::Audience => 'warning',
        };
    }
}
