<?php

use Symfony\Component\Finder\Finder;

// Independent de bootstrap-ul Laravel (test static pe conținut de fișiere, nu are nevoie de
// aplicație/DB) — calculăm rădăcina proiectului direct din calea acestui fișier.
$projectRoot = dirname(__DIR__, 2);

/**
 * Fonturile de brand ale site-ului public (Proxima Nova, Cervino) sunt CFF/PostScript FĂRĂ
 * hinting. Măsurat direct (canvas, același rasterizator ca DOM-ul): la dimensiuni întregi
 * IMPARE mici — exact 17px și 19px — dotul lui „i"/„j" se lipește de tijă și litera arată ca o
 * bară (0/12 poziții cu dot separat), în timp ce 16/18/20px randează curat (12/12). Confirmat că
 * `text-rendering` NU are niciun efect (identic pe auto/optimizeLegibility/optimizeSpeed) — cauza
 * e strict hinting-ul lipsă la acele două dimensiuni.
 *
 * Fix-ul (bază `.site-shell` 17px→18px + toate clamp()-urile de text din paginile publice) a fost
 * revenit o dată de o editare concurentă în acest repo. Acest test transformă regresia dintr-un
 * bug tăcut într-un eșec zgomotos al suitei — orice reapariție a 1.0625rem (17px) sau 1.1875rem
 * (19px) în CSS-ul/componentele site-ului public pică imediat la `php artisan test`.
 */
it('nu regresează font-size-ul de bază al .site-shell la 17px (bug randare literei „i")', function () use ($projectRoot) {
    $css = file_get_contents($projectRoot.'/resources/css/app.css');

    $start = strpos($css, '.site-shell {');

    // .site-shell a regresat la 17px sau regula a dispărut din resources/css/app.css.
    expect($start)->not->toBeFalse();

    // Fereastră scurtă după declarația regulii — suficientă pt. font-size (declarat imediat după
    // font-family), fără să presupunem unde se închide blocul (.site-shell înlănțuie mulți tokeni).
    $window = substr($css, $start, 1200);

    // .site-shell a regresat la 17px — vezi comentariul din resources/css/app.css.
    expect($window)->not->toContain('font-size: 1.0625rem');
    // .site-shell a regresat la 19px — vezi comentariul din resources/css/app.css.
    expect($window)->not->toContain('font-size: 1.1875rem');
    // .site-shell nu mai are dimensiunea de bază așteptată (18px).
    expect($window)->toContain('font-size: 1.125rem');
});

it('nu regresează clamp()-urile de text din paginile publice la 17px/19px (bug randare literei „i")', function () use ($projectRoot) {
    $offenders = [];

    $files = Finder::create()
        ->in($projectRoot.'/resources/js/pages/public')
        ->in($projectRoot.'/resources/js/components/public')
        ->name('*.tsx')
        ->files();

    foreach ($files as $file) {
        $contents = $file->getContents();

        if (str_contains($contents, '1.0625rem') || str_contains($contents, '1.1875rem')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    // Fișiere cu 1.0625rem (17px) sau 1.1875rem (19px) reintroduse — bug randare literei „i" pe
    // fonturile de brand CFF. Fix: 1.0625rem→1.125rem, 1.1875rem→1.25rem.
    expect($offenders)->toBe([]);
});
