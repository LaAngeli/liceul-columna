<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Starea unei sesiuni de corigență în fluxul de aprobare (spec §2.5 / #33):
 * DRAFT (propusă de vicedirectorul pe instruire) → APPROVED (aprobată prin ordinul directorului)
 * → PUBLISHED (încărcată în catalog de administratorul operațional, vizibilă familiilor).
 */
enum CorigentaSessionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return (string) trans('enums.corigenta_session_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'warning',
            self::Published => 'success',
        };
    }
}
