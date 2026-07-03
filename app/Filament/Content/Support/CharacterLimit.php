<?php

namespace App\Filament\Content\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

/**
 * Aplică o limită de caractere UNIFORMĂ pe un câmp TextInput/Textarea:
 *  - oprește fizic tastarea/lipirea peste limită — atribut HTML nativ `maxlength`, forțat prin
 *    `extraInputAttributes()`. Filament SUPRIMĂ implicit `maxlength` pentru câmpurile aflate într-un
 *    `Tabs` (ca să evite un bug cunoscut de focus pe câmpuri dintr-un tab neactiv) — toate formularele
 *    din Studio sunt tabulate, deci `->maxLength()` singur NU oprea tastarea; `extraInputAttributes()`
 *    ocolește acea suprimare (confirmat direct în vendor: bag-ul propriu câștigă la merge);
 *  - afișează un contor „X / MAX" LIVE lângă etichetă (`hint()`), roșu la atingerea limitei.
 *    Actualizarea e 100% CLIENT-SIDE prin Alpine `$store` global, indexat pe numele câmpului — fără
 *    NICIUN round-trip Livewire la fiecare tastă. Se refresh-uiește instant, chiar în timpul tastării.
 *
 * Nota tehnică pentru sesiuni viitoare: hint-ul se randează într-un container Alpine SEPARAT de
 * input (Filament schema component), deci un `x-data` local al input-ului NU e vizibil hint-ului.
 * Puntea e `Alpine.$store.characterCount` — un obiect global indexat pe numele câmpului. Am ales
 * `getName()` pentru cheie (nu `getStatePath()`) fiindcă `getStatePath()` cere containerul deja
 * bootstrap-uit, ceea ce NU e cazul la momentul în care `apply()` e chemat din `form()`.
 */
class CharacterLimit
{
    public static function apply(TextInput|Textarea $field, int $max): void
    {
        $key = $field->getName();

        $field
            ->extraInputAttributes([
                'maxlength' => $max,
                // Marcaj citit de sincronizatorul global (ContentPanelProvider → SCRIPTS_AFTER): după
                // fiecare commit Livewire, contorul se realiniază din valoarea reală — necesar pentru
                // câmpurile completate programatic (slug/rezumat auto-derivat), unde nu se declanșează
                // evenimentul `input`.
                'data-char-count-key' => $key,
                'x-on:input' => "\$store.characterCount['{$key}'] = \$el.value.length",
                // Init store din valoarea curentă + pornește (o singură dată, ghidat de `window`) un
                // reconciliator pe setInterval: câmpurile completate PROGRAMATIC (slug/rezumat
                // auto-derivat) primesc valoarea prin entangle-ul Alpine al Filament, care NU declanșează
                // `input` și se aplică DUPĂ orice hook Livewire — deci un simplu event/hook ratează
                // momentul. Reconciliatorul citește lungimea reală și scrie în store DOAR la schimbare
                // (cost imperceptibil). Folosim `setInterval` (repetat de browser), NU un rAF
                // auto-reprogramat: referința recursivă a funcției nu supraviețuiește scope-ului în care
                // Alpine evaluează `x-init`. Rulează din x-init fiindcă un <script> din render-hook,
                // fiind în HTML-ul componentei Livewire full-page, nu se execută la hidratare.
                'x-init' => "\$store.characterCount = \$store.characterCount || {}; \$store.characterCount['{$key}'] = \$el.value.length;"
                    .' if (! window.__ccLoop) { window.__ccLoop = setInterval(function () {'
                    ." var s = (window.Alpine && Alpine.store) ? Alpine.store('characterCount') : null;"
                    .' if (! s) { return; }'
                    ." document.querySelectorAll('[data-char-count-key]').forEach(function (e) {"
                    ." var k = e.getAttribute('data-char-count-key'), n = (e.value || '').length;"
                    .' if (s[k] !== n) { s[k] = n; } }); }, 120); }',
            ])
            ->hint(new HtmlString(
                '<span x-data '.
                'x-text="`${(($store.characterCount || {})[\''.$key.'\']) ?? 0} / '.$max.'`" '.
                'x-bind:style="((($store.characterCount || {})[\''.$key.'\']) ?? 0) >= '.$max.' ? \'color: var(--danger-600)\' : \'\'">'.
                '0 / '.$max.
                '</span>',
            ));
    }
}
