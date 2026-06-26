<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Starea generică a unei cereri cu aprobare (ex. motivare de absențe): în așteptare,
 * aprobată, respinsă.
 */
enum RequestStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'În așteptare',
            self::Approved => 'Aprobată',
            self::Rejected => 'Respinsă',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
