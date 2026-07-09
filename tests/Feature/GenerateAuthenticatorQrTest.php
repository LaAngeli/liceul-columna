<?php

use Illuminate\Support\Facades\File;

it('generează QR-uri SVG valide pentru Android și iOS', function () {
    $dir = public_path('images/authenticator');
    $files = [
        $dir.'/google-authenticator-android.svg',
        $dir.'/google-authenticator-ios.svg',
    ];

    // Le ștergem întâi ca să dovedim că le (re)creează comanda, nu că erau deja acolo.
    foreach ($files as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }

    $this->artisan('app:generate-authenticator-qr')->assertSuccessful();

    foreach ($files as $file) {
        expect(File::exists($file))->toBeTrue();

        $svg = File::get($file);
        expect($svg)->toContain('<svg')
            ->and($svg)->toContain('viewBox')
            // navy de brand pe alb — scanabil pe orice temă
            ->and($svg)->toContain('#0f4d77');
    }
});
