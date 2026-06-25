<?php

namespace App\Enums;

enum Sex: string
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
}
