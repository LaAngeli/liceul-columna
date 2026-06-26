<?php

namespace App\Enums;

use App\Models\AbsenceMotivation;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipurile de cereri tipice pe care familia le poate depune (spec §4.3). Motivarea absențelor are
 * fluxul ei separat ({@see AbsenceMotivation}), deci nu apare aici.
 */
enum DocumentRequestType: string implements HasLabel
{
    case Invoire = 'invoire';
    case Adeverinta = 'adeverinta';
    case Transfer = 'transfer';
    case Contestatie = 'contestatie';
    case Sedinta = 'sedinta';

    public function label(): string
    {
        return match ($this) {
            self::Invoire => 'Cerere de învoire / absență planificată',
            self::Adeverinta => 'Cerere de adeverință de elev',
            self::Transfer => 'Cerere de transfer / retragere',
            self::Contestatie => 'Cerere de reexaminare / contestație a unei note',
            self::Sedinta => 'Cerere de programare a unei ședințe',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Cererile care vizează un interval de timp (au nevoie de perioadă).
     */
    public function needsPeriod(): bool
    {
        return $this === self::Invoire;
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
