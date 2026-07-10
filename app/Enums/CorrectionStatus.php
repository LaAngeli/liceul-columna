<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Starea unei solicitări de corecție a unei note (§3.1): cerută de profesor/diriginte,
 * aprobată de prim-vicedirector (excepțional, director).
 *
 * `Expired` = cererea a rămas fără obiect pentru că nota a fost ANULATĂ între timp.
 * `Withdrawn` = solicitantul a retras-o înainte de a fi judecată.
 *
 * Niciuna nu e o respingere — administrația nu s-a pronunțat asupra lor — de aceea au stări
 * proprii. Cererile nu se șterg: rămân în arhivă (§1).
 */
enum CorrectionStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return (string) trans('enums.correction_status.'.$this->value);
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
            self::Expired, self::Withdrawn => 'gray',
        };
    }
}
