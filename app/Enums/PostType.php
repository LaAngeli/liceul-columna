<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Categoriile de articole administrate în Studio. Sursă UNICĂ pentru valorile + etichetele RO.
 *
 * NU e cast pe `Post::category` (coloana rămâne string, folosită ca atare în BlogController, scope
 * și frontend) — enum-ul e folosit doar pentru scoping-ul resurselor + Select-uri, fără ripple.
 */
enum PostType: string implements HasLabel
{
    case Blog = 'blog';
    case Actualitati = 'actualitati';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blog => 'Blog',
            self::Actualitati => 'Actualități și evenimente',
        };
    }
}
