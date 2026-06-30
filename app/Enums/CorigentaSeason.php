<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sezonul unei sesiuni de lichidare a corigenței (spec §2.5 / #33): IARNA (vacanța intrasemestrială,
 * pentru corigențele din semestrul I) și VARA (sfârșit de an, pentru sem. II / anual).
 */
enum CorigentaSeason: string implements HasLabel
{
    case Iarna = 'iarna';
    case Vara = 'vara';

    public function label(): string
    {
        return (string) trans('enums.corigenta_season.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
