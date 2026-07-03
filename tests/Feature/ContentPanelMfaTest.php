<?php

use App\Filament\MultiFactor\AppAuthentication;
use App\Models\Admin;
use Filament\Facades\Filament;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Regresie: Filament 4.11.7 dublu-încapsulează QR-ul SVG ca data-URI atunci când
 * `bacon/bacon-qr-code` e instalat dar extensia PHP `imagick` lipsește (cazul acestui server) —
 * `pragmarx/google2fa-qrcode` 4.0.0 face deja acea încapsulare intern. Rezultatul dublu-încapsulat
 * decodează la un STRING (alt data-URI ca text), nu la SVG, deci imaginea QR nu se randează în
 * modalul de configurare MFA. {@see AppAuthentication} elimină încapsularea
 * suplimentară a Filament.
 */
it('generează un data-URI SVG valid și decodabil pentru codul QR de MFA', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    $secret = app(Google2FA::class)->generateSecretKey(16);
    $dataUri = app(AppAuthentication::class)->generateQrCodeDataUri($secret);

    expect($dataUri)->toStartWith('data:image/svg+xml;base64,');

    [, $base64] = explode(',', $dataUri, 2);
    $decoded = base64_decode($base64, true);

    expect($decoded)->not->toBeFalse()
        ->and($decoded)->toContain('<svg')
        // Regresie directă: varianta buggy dublu-încapsulată decodează la un STRING „data:image/…”,
        // nu la marcaj SVG.
        ->and($decoded)->not->toStartWith('data:image/svg+xml;base64,');
});
