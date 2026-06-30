<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A doua limbă străină studiată (legacy bdn_elevi.str_2).
 */
enum SecondLanguage: string implements HasLabel
{
    case French = 'fr';
    case German = 'gm';
    case None = 'nu';

    public function label(): string
    {
        return (string) trans('enums.second_language.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
