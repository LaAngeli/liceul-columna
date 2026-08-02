<?php

declare(strict_types=1);

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatarele panoului, generate LOCAL ca SVG (data URI) din {@see Initials}.
 *
 * Înlocuiește `UiAvatarsProvider`, providerul implicit al Filament, din două motive:
 *
 *  1. CONSECVENȚĂ: el trimitea prima literă a fiecărui cuvânt („[ U V"), iar serviciul extern
 *     afișa prima+ultima („[V", care în bară arăta ca „IV") — alt rezultat decât widget-ul de pe
 *     panoul de control pentru ACELAȘI om. Acum toate avatarele trec prin aceeași regulă.
 *  2. DATE PERSONALE: providerul implicit construia `https://ui-avatars.com/api/?name=…`, deci
 *     numele utilizatorului (personal al școlii — și, oriunde s-ar folosi pe fișe, al unui MINOR)
 *     pleca la fiecare randare către un serviciu terț, împreună cu IP-ul și referer-ul. Cu
 *     generare locală nu iese nimic din aplicație și avatarele funcționează și fără internet.
 *
 * Culorile urmează avatarul din widget-ul „Bun venit" (verdele de brand pe contrastul lui), ca
 * bara de sus și panoul de control să arate la fel.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    private const BACKGROUND = '#9bc31e';

    private const FOREGROUND = '#1d1d1c';

    public function get(Model|Authenticatable $record): string
    {
        $initials = Initials::for(Filament::getNameForDefaultAvatar($record));

        // `·` ca la fișa elevului: un cerc gol arată a avatar stricat, nu a lipsă de nume.
        $label = $initials !== '' ? $initials : '·';

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
                <rect width="100" height="100" fill="{$this->background()}"/>
                <text x="50" y="50" fill="{$this->foreground()}" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="42" font-weight="700" letter-spacing="1" text-anchor="middle" dominant-baseline="central">{$this->escape($label)}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function background(): string
    {
        return self::BACKGROUND;
    }

    private function foreground(): string
    {
        return self::FOREGROUND;
    }

    /** Numele ajung în SVG — escapate, ca un „&" sau „<" din nume să nu rupă documentul. */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
