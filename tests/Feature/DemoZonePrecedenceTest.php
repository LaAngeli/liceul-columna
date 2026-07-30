<?php

/**
 * ZONA DEMO ARE ÎNTÂIETATE asupra legării conturilor de fișe reale (2026-07-27).
 *
 * Principiul fazei de test: demo și real strict delimitate, ca la go-live curățarea să fie totală
 * și sigură. `app:link-demo-accounts` lega conturile demo de fișe REALE — util pentru paritatea
 * local↔producție, dar exact opusul delimitării: testerii ajungeau să producă note, absențe și
 * mesaje pe elevi REALI, iar ce fac prin interfață nu intră în niciun manifest.
 *
 * Testul fixează garda, ca cele două abordări să nu se mai suprascrie una pe alta la fiecare rulare.
 */

use Illuminate\Support\Facades\File;

/**
 * ⚠️ `storage_path()` NU e izolat în teste — un `File::delete()` naiv ștergea manifestul REAL al
 * zonei demo din mediul de dezvoltare, iar cu el legăturile salvate pentru `--remove`
 * (pățit 2026-07-30). Starea se salvează și se pune la loc.
 */
beforeEach(function () {
    $this->zonePath = storage_path('app/demo/zone.json');
    $this->zoneBackup = File::exists($this->zonePath) ? File::get($this->zonePath) : null;
});

afterEach(function () {
    if ($this->zoneBackup !== null) {
        File::ensureDirectoryExists(dirname($this->zonePath));
        File::put($this->zonePath, $this->zoneBackup);

        return;
    }

    File::delete($this->zonePath);
});

it('refuză să lege conturile demo de fișe reale cât timp există o zonă demo', function () {
    File::ensureDirectoryExists(storage_path('app/demo'));
    File::put(storage_path('app/demo/zone.json'), '{}');

    $this->artisan('app:link-demo-accounts')
        ->expectsOutputToContain('zonă demo activă')
        ->assertFailed();
});

it('rulează normal pe un mediu fără zonă demo', function () {
    File::delete(storage_path('app/demo/zone.json'));

    $this->artisan('app:link-demo-accounts')->assertSuccessful();
});
