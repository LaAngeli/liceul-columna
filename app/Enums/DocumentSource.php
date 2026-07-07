<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sursa unui document (spec §1/§3): `static` = fișier încărcat de administratorul operațional
 * (regulamente, ghiduri, șabloane — stocat + versionat); `generated` = produs automat din datele
 * catalogului (rapoarte, cereri pre-completate — la cerere, cu gardurile aplicate). Biblioteca
 * Fazei 1 gestionează documente `static`; cele `generated` sunt legate din funcțiile existente.
 */
enum DocumentSource: string implements HasLabel
{
    case Static = 'static';
    case Generated = 'generated';

    public function getLabel(): string
    {
        return (string) trans('enums.document_source.'.$this->value);
    }
}
