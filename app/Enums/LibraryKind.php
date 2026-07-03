<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul unei categorii din Bibliotecă. `literature` = beletristică (frontend-ul o grupează pe
 * index alfabetic autor); `documents` = curricula/ghiduri/repere (listă simplă).
 */
enum LibraryKind: string implements HasLabel
{
    case Literature = 'literature';
    case Documents = 'documents';

    public function getLabel(): string
    {
        return match ($this) {
            self::Literature => 'Literatură (grupare alfabetică pe autor)',
            self::Documents => 'Documente (listă simplă)',
        };
    }
}
