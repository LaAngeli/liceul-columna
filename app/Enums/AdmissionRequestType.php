<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul unei cereri din zona de admitere:
 * - `Visit` = programare vizită (acțiune cu angajament mic, mereu disponibilă; are dată/oră).
 * - `Enrollment` = cerere de înmatriculare (acțiune cu angajament mare).
 */
enum AdmissionRequestType: string implements HasLabel
{
    case Visit = 'visit';
    case Enrollment = 'enrollment';

    public function label(): string
    {
        return (string) trans('enums.admission_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Visit => 'info',
            self::Enrollment => 'success',
        };
    }
}
