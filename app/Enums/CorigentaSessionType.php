<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul sesiunii de corigență: de BAZĂ (prima sesiune) și REPETATĂ (pentru cei care nu promovează
 * la sesiunea de bază). Vară: bază în ultima săptămână din august, repetată la începutul anului;
 * iarnă: bază în vacanța de Crăciun, repetată în prima săptămână din sem. II.
 */
enum CorigentaSessionType: string implements HasLabel
{
    case Baza = 'baza';
    case Repetata = 'repetata';

    public function label(): string
    {
        return match ($this) {
            self::Baza => 'Sesiune de bază',
            self::Repetata => 'Sesiune repetată',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
