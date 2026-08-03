<?php

use App\Filament\Pages\ClassRegister;
use App\Filament\Pages\MyNotifications;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Support\Locale;
use App\Support\PanelGuide;

/**
 * Ghidul secțiunilor: iconița „i" de lângă titlul paginii.
 *
 * Testele apără cele trei feluri în care mecanismul poate putrezi tăcut: o clasă redenumită sau
 * ștearsă (harta ar arăta spre nimic), o cheie fără traducere (secțiunea și-ar pierde explicația
 * exact în limba în care e mai greu de ghicit), și legarea pe resursă (dacă s-ar rupe, ghidul ar
 * apărea pe listare dar nu și pe fișă).
 */
it('fiecare clasă din hartă există cu adevărat', function () {
    $reflection = new ReflectionClass(PanelGuide::class);
    /** @var array<string, string> $map */
    $map = $reflection->getConstant('MAP');

    $missing = array_values(array_filter(
        array_keys($map),
        fn (string $class): bool => ! class_exists($class),
    ));

    expect($missing)->toBe([]);
});

it('fiecare cheie are text în TOATE limbile', function (string $locale) {
    app()->setLocale($locale);

    $missing = [];

    foreach (PanelGuide::keys() as $key) {
        $text = trans('guide.'.$key);

        if (! is_string($text) || $text === '' || $text === 'guide.'.$key) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([]);
})->with(array_keys(Locale::supported()));

it('ghidul se leagă pe RESURSĂ, deci acoperă listarea și fișa deodată', function () {
    // Filament trimite ambele clase în `scopes`; potrivirea pe resursă înseamnă că paginile ei
    // (listare, creare, editare, vizualizare) primesc același ghid, fără cablare separată.
    expect(PanelGuide::keyFor([ListGrades::class, GradeResource::class]))->toBe('grades')
        ->and(PanelGuide::keyFor([GradeResource::class]))->toBe('grades')
        // Paginile custom se cheamă pe clasa lor proprie.
        ->and(PanelGuide::keyFor([ClassRegister::class]))->toBe('class_register');
});

it('o secțiune fără ghid nu randează nimic', function () {
    // Mai bine lipsă decât o iconiță goală sau o cheie brută lângă titlu.
    expect(PanelGuide::keyFor([MyNotifications::class]))->toBeNull()
        ->and(PanelGuide::render([MyNotifications::class]))->toBe('');
});

it('ghidul randat poartă textul și un nume accesibil', function () {
    app()->setLocale('ro');

    $html = PanelGuide::render([GradeResource::class]);

    expect($html)->toContain('fi-guide-hint')
        ->and($html)->toContain(__('guide.aria_label'))
        // Textul ajunge în conținutul vizual-ascuns (numele accesibil al butonului): tooltipul
        // Tippy e desenat de JS, deci cititorul de ecran nu-l vede.
        ->and($html)->toContain('Nota nu se șterge niciodată')
        // FĂRĂ `title`: ar fi produs un al doilea tooltip, cel nativ al browserului.
        ->and($html)->not->toContain('title=');
});
