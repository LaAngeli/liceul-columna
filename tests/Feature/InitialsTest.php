<?php

/**
 * Inițialele avatarelor — o SINGURĂ regulă pe toată platforma (raportat 2026-08-02: același
 * director apărea cu „IV" în bara de sus și „DU" pe panoul de control).
 *
 * Regula e oglindită în `resources/js/hooks/use-initials.tsx`; cazurile de mai jos sunt aceleași
 * în ambele suite, ca divergențele să iasă la iveală într-una din ele.
 */

use App\Models\User;
use App\Support\Initials;
use App\Support\InitialsAvatarProvider;

it('compune inițialele din primele două cuvinte, ignorând marcajul [DEMO]', function (?string $name, string $expected) {
    expect(Initials::for($name))->toBe($expected);
})->with([
    'nume simplu' => ['Ursu Valentin', 'UV'],
    'marcaj demo ignorat' => ['[DEMO] Ursu Valentin', 'UV'],
    'marcajul nu mai dă litera lui' => ['[DEMO] Cojocaru Alexandru', 'CA'],
    'trei cuvinte → primele două' => ['Bujor-Cobili Carolina Maria', 'BC'],
    'nume compus cu cratimă' => ['Bujor-Cobili Carolina', 'BC'],
    'un singur cuvânt' => ['Madonna', 'M'],
    'diacritice majusculate' => ['Șerban Ștefan', 'ȘȘ'],
    'cuvinte fără litere sărite' => ['— Popescu Ion', 'PI'],
    'spații multiple' => ['  Ursu   Valentin  ', 'UV'],
    'doar marcaj' => ['[DEMO]', ''],
    'gol' => ['', ''],
    'null' => [null, ''],
]);

it('avatarul panoului se generează LOCAL, cu aceleași inițiale (fără serviciu extern)', function () {
    $user = User::factory()->create(['name' => '[DEMO] Ursu Valentin']);

    $url = (new InitialsAvatarProvider)->get($user);

    // Nicio cerere externă: numele utilizatorului nu părăsește aplicația.
    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->and($url)->not->toContain('ui-avatars.com');

    $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $url), true);

    expect($svg)->toContain('>UV<');
});

it('numele fără litere primesc un avatar cu punct, nu unul gol', function () {
    $user = User::factory()->create(['name' => '[DEMO]']);

    $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', (new InitialsAvatarProvider)->get($user)), true);

    expect($svg)->toContain('>·<');
});

it('caracterele speciale din nume nu rup SVG-ul', function () {
    $user = User::factory()->create(['name' => '<Ana> &Maria']);

    $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', (new InitialsAvatarProvider)->get($user)), true);

    // „A" + „M"; simbolurile scapă escapate, nu ca marcaj.
    expect($svg)->toContain('>AM<')
        ->and(simplexml_load_string($svg))->not->toBeFalse();
});
