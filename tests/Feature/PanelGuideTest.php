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

it('ghidurile de CÂMP sunt traduse în toate limbile și chiar folosite în formulare', function (string $locale) {
    app()->setLocale($locale);

    // Cheile se descoperă din fișierul RO (sursa), nu dintr-o listă scrisă de mână, care s-ar fi
    // desincronizat la prima adăugare.
    /** @var array<string, string> $fields */
    $fields = trans('guide.fields', [], 'ro');

    expect($fields)->not->toBeEmpty();

    // Codul panoului, citit o singură dată: căutăm în el fiecare cheie.
    $panelSource = '';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament'))) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $panelSource .= (string) file_get_contents($file->getPathname());
        }
    }

    $missing = [];
    $unused = [];

    foreach (array_keys($fields) as $key) {
        if (PanelGuide::field($key) === null) {
            $missing[] = $key;
        }

        // O cheie de ghid pe care n-o folosește niciun formular e text mort: nimeni n-o vede,
        // dar cineva o va traduce la fiecare limbă nouă. Ambele porți de intrare contează:
        // `hint()` (componenta completă, calea normală) și `field()` (doar textul).
        if (! str_contains($panelSource, "PanelGuide::hint('".$key."')")
            && ! str_contains($panelSource, "PanelGuide::field('".$key."')")) {
            $unused[] = $key;
        }
    }

    expect($missing)->toBe([])
        ->and($unused)->toBe([]);
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
        ->and($html)->toContain('Notele nu se șterg')
        // FĂRĂ `title`: ar fi produs un al doilea tooltip, cel nativ al browserului.
        ->and($html)->not->toContain('title=');
});

it('bula nu se deschide la focusul mutat de altcineva', function () {
    /**
     * REGRESIE (raport beneficiar, 04.08.2026): la deschiderea modalului „Desemnare în masă",
     * explicația „i" apărea singură, peste formular.
     *
     * Cauza nu era în ghid, ci în vecinătate: modalul Filament își mută singur focusul la
     * deschidere, iar când primul câmp are ghid, capcana de focus aterizează exact pe butonul
     * ăsta — și Tippy se declanșează pe `focus`, nu doar pe hover. Deosebirea o face
     * `:focus-visible`: Tab îl aprinde, focusul programatic după un click de mouse nu.
     *
     * Verificat în browser: cu mouse-ul bula tace (0 bule), la Tab și la hover apare.
     * Testul apără doar garda din markup — dacă dispare, comportamentul se întoarce tăcut.
     */
    $html = PanelGuide::render([GradeResource::class]);

    expect($html)->toContain('onShow')
        ->and($html)->toContain(':focus-visible')
        // Hover-ul și atingerea nu trec prin `focus`, deci garda le lasă neatinse.
        ->and($html)->toContain("'focusin'");
});

/**
 * Regula de scriere, apărată automat (raport beneficiar, 04.08.2026: „textul nu e informativ și
 * uneori greu de înțeles").
 *
 * Prima variantă a ghidurilor era scrisă în limbaj de dezvoltator — „filtrul e aplicat pe server",
 * „indexurile unice văd rândurile șterse", „într-o singură tranzacție" — plus MAJUSCULE de accent
 * și fraze cu trei idei legate prin liniuțe. Testul nu poate judeca stilul, dar poate opri exact
 * lucrurile care au făcut textele greu de citit, ca ele să nu se strecoare înapoi la prima adăugare.
 */
it('ghidurile nu folosesc termeni de sistem și nu strigă cu majuscule', function (string $locale) {
    app()->setLocale($locale);

    /** @var array<string, string> $fields */
    $fields = trans('guide.fields');

    $texts = [];

    foreach (PanelGuide::keys() as $key) {
        $texts['guide.'.$key] = (string) trans('guide.'.$key);
    }

    foreach ($fields as $key => $text) {
        $texts['guide.fields.'.$key] = (string) $text;
    }

    // Cuvinte care descriu MECANISMUL, nu efectul. „Server" și „tranzacție" n-au ce căuta în
    // explicația dată unui profesor; restul sunt termeni pe care nimeni din școală nu-i folosește.
    $systemWords = ['server', 'tranzacți', 'транзакц', 'сервер', 'transaction', 'index unic', 'индекс', 'unique index', 'URL', 'onboarding'];

    $offenders = [];

    foreach ($texts as $key => $text) {
        foreach ($systemWords as $word) {
            if (mb_stripos($text, $word) !== false) {
                $offenders[] = $key.' → „'.$word.'"';
            }
        }

        // Un cuvânt de 4+ litere scris integral cu majuscule = accent strigat. Abrevierile scurte
        // (PDF, ESS, RO) rămân permise: ele chiar se scriu așa.
        if (preg_match('/(?<![\p{Lu}])\p{Lu}{4,}(?![\p{Ll}])/u', $text, $m) === 1) {
            $offenders[] = $key.' → majuscule: '.$m[0];
        }
    }

    expect($offenders)->toBe([]);
})->with(array_keys(Locale::supported()));
