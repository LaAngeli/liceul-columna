<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Statutul elevului la final de semestru/an (§2.5): promovat (toate mediile ≥ 5),
 * corigent (cel puțin o medie < 5), amânat (situația nu poate fi definitivată).
 */
enum StudentStatus: string implements HasLabel
{
    case Promovat = 'promovat';
    case Corigent = 'corigent';
    case Amanat = 'amanat';

    public function label(): string
    {
        return (string) trans('enums.student_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Promovat => 'success',
            self::Corigent => 'danger',
            self::Amanat => 'warning',
        };
    }
}
