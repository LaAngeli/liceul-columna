<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Sex: string implements HasLabel
{
    case Female = 'f';
    case Male = 'm';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Feminin',
            self::Male => 'Masculin',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
