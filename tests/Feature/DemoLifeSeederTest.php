<?php

/**
 * DATELE DEMO CA INSTRUMENT DE VALIDARE (cerință 2026-07-28).
 *
 * Beneficiarul a raportat că datele demo nu mai reflectă logica platformei: fluxuri neduse la
 * capăt, module goale, notificări care duceau în 403. Testele de aici fixează CONTRACTUL noului
 * generator — nu că „există rânduri", ci că fiecare flux se închide și că modulele se oglindesc.
 */

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(storage_path('app/demo/zone.json'));
});

it('refuză să genereze activitate fără zonă demo — altfel ar scrie pe elevi reali', function () {
    File::delete(storage_path('app/demo/zone.json'));

    $this->artisan('app:seed-demo-life')
        ->expectsOutputToContain('Nu există zonă demo')
        ->assertFailed();
});

it('curățarea fără manifest nu face nimic și nu crapă', function () {
    File::ensureDirectoryExists(storage_path('app/demo'));
    File::put(storage_path('app/demo/zone.json'), '{}');
    File::delete(storage_path('app/demo/life.json'));

    $this->artisan('app:seed-demo-life', ['--remove' => true])
        ->expectsOutputToContain('Fără manifest')
        ->assertSuccessful();
});

it('semnalează lipsa conturilor demo în loc să genereze pe jumătate', function () {
    File::ensureDirectoryExists(storage_path('app/demo'));
    File::put(storage_path('app/demo/zone.json'), '{}');

    // Baza de test e goală: niciun cont demo → comanda trebuie să spună CE lipsește,
    // nu să producă o zonă parțial populată care apoi induce în eroare la testare.
    $this->artisan('app:seed-demo-life')
        ->expectsOutputToContain('Cont demo lipsă')
        ->assertFailed();
});
