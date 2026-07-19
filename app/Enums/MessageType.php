<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul unui mesaj în modulul de comunicare (spec §4):
 * - Direct: comunicare liberă spre nivelul firesc (familie ↔ profesor/diriginte);
 * - Audience: „Solicitare audiență / sesizare" — rutată spre prim-vicedirector (familia NU scrie
 *   direct conducerii, §4.2), escaladabilă spre director;
 * - Behavioral: semnalare de COMPORTAMENT a unui elev, de la profesor — FILTRATĂ: merge la
 *   prim-vicedirector (moderare), nu direct la familie; vicedirectorul decide ce transmite (§4.2).
 */
enum MessageType: string implements HasLabel
{
    case Direct = 'direct';
    case Audience = 'audience';
    case Behavioral = 'behavioral';

    public function label(): string
    {
        return (string) trans('enums.message_type.'.$this->value);
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
            self::Behavioral => 'danger',
        };
    }
}
