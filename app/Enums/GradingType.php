<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Modul de notare al unei discipline (legacy bdn_disc.notare).
 */
enum GradingType: string implements HasLabel
{
    case Numeric = 'n';                 // notă numerică (1–10)
    case Calificativ = 'c';             // calificativ (FB/B/S/...)
    case CalificativDescriptiv = 'cd';  // calificativ + descriptor
    case Descriptiv = 'd';              // doar descriptiv

    public function label(): string
    {
        return (string) trans('enums.grading_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
