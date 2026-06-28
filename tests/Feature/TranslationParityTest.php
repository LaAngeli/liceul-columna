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
