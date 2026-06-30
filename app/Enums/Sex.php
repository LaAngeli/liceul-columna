<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Sex: string implements HasLabel
{
    case Female = 'f';
    case Male = 'm';

    public function label(): string
    {
        return (string) trans('enums.sex.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
