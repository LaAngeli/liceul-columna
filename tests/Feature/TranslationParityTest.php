<?php

/**
 * Paritatea cheilor de traducere (spec §9 / #41): fișierele structurate de UI trebuie să aibă EXACT
 * aceleași chei în RO, RU și EN — altfel un text rămâne netradus (fallback RO) fără să observăm.
 * Testul listează cheile lipsă, ca să fie ușor de completat.
 */

/**
 * @param  array<mixed>  $array
 * @return list<string>
 */
function flattenTranslationKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = array_merge($keys, flattenTranslationKeys($value, $full));
        } else {
            $keys[] = $full;
        }
    }

    return $keys;
}

dataset('uiTranslationFiles', ['site', 'privacy']);

it('are aceleași chei în RO/RU/EN', function (string $file) {
    $ro = require lang_path("ro/{$file}.php");
    $ru = require lang_path("ru/{$file}.php");
    $en = require lang_path("en/{$file}.php");

    $roKeys = flattenTranslationKeys($ro);

    $missingRu = array_values(array_diff($roKeys, flattenTranslationKeys($ru)));
    $extraRu = array_values(array_diff(flattenTranslationKeys($ru), $roKeys));
    $missingEn = array_values(array_diff($roKeys, flattenTranslationKeys($en)));
    $extraEn = array_values(array_diff(flattenTranslationKeys($en), $roKeys));

    expect($missingRu)->toBe([], "{$file}: chei lipsă în RU: ".implode(', ', $missingRu))
        ->and($extraRu)->toBe([], "{$file}: chei în plus în RU: ".implode(', ', $extraRu))
        ->and($missingEn)->toBe([], "{$file}: chei lipsă în EN: ".implode(', ', $missingEn))
        ->and($extraEn)->toBe([], "{$file}: chei în plus în EN: ".implode(', ', $extraEn));
})->with('uiTranslationFiles');

/**
 * Textele EXPLICATIVE din cabinetul familiei nu strigă cu majuscule.
 *
 * REGRESIE (raport beneficiar, 04.08.2026, pe ghidurile din panou): accentul scris cu MAJUSCULE se
 * citește ca ridicare de ton, nu ca subliniere. Aceleași texte existau și în cabinet — „MEDIA",
 * „URCAT", „EXPEDIATE" — unde cititorul e un părinte sau un elev, deci și mai puțin dispus să
 * descifreze. Verificarea prinde exact tiparul care s-a strecurat: un cuvânt întreg cu majuscule.
 *
 * De ce doar cheile explicative (`_hint`, `_desc`, `_legend`, `extract_`): ele există ca să fie
 * CITITE. Etichetele scurte pot avea nevoie legitimă de un acronim; dacă unul chiar trebuie să
 * apară într-o explicație, testul care cade e un semnal util — merită întrebat dacă părintele îl
 * înțelege.
 */
it('explicațiile din cabinet nu strigă cu majuscule', function (string $locale) {
    $site = require lang_path("{$locale}/site.php");

    /** @var array<string, string> $texts */
    $texts = [];

    foreach (['cabinet', 'dashboard', 'profile', 'settings'] as $group) {
        foreach ($site[$group] ?? [] as $key => $value) {
            if (is_string($value) && preg_match('/(_hint$|_desc$|_legend$|^legend_|^extract_|_note$|_help$)/', (string) $key) === 1) {
                $texts[$group.'.'.$key] = $value;
            }
        }
    }

    foreach (require lang_path("{$locale}/cabinet_flash.php") as $key => $value) {
        if (is_string($value)) {
            $texts['cabinet_flash.'.$key] = $value;
        }
    }

    expect($texts)->not->toBeEmpty();

    $shouty = [];

    foreach ($texts as $key => $text) {
        // 4+ litere majuscule la rând, ca un cuvânt de sine stătător. Abrevierile scurte care chiar
        // se scriu așa (PDF, ESS, RO) rămân permise.
        if (preg_match('/(?<![\p{Lu}])\p{Lu}{4,}(?![\p{Ll}])/u', strip_tags($text), $match) === 1) {
            $shouty[] = $key.' → '.$match[0];
        }
    }

    expect($shouty)->toBe([], 'Majuscule de accent: '.implode(', ', $shouty));
})->with(['ro', 'ru', 'en']);
