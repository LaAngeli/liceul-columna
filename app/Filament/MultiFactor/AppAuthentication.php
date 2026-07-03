<?php

namespace App\Filament\MultiFactor;

use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * Corectează un bug de compatibilitate între versiunile instalate: Filament (4.11.7) încapsulează
 * SVG-ul QR ca data-URI o A DOUA oară („fallback pentru bacon-qr-code fără imagick"), dar
 * `pragmarx/google2fa-qrcode` (4.0.0) instalat aici face deja acea încapsulare intern, atât pentru
 * backend-ul Bacon cât și Chillerlan. Dubla încapsulare produce un data-URI invalid — imaginea QR
 * nu se randează la configurarea MFA (reprodus: extensia PHP `imagick` lipsește pe acest server).
 *
 * Fără extensia `imagick` (necesită un DLL nativ pe Windows), fixul corect e la acest nivel:
 * întoarcem direct rezultatul (deja corect încapsulat), fără încapsularea suplimentară a Filament.
 */
class AppAuthentication extends BaseAppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        /** @var HasAppAuthentication $user */
        $user = Filament::auth()->user();

        return $this->google2FA->getQRCodeInline(
            $this->getBrandName(),
            $this->getHolderName($user),
            $secret,
        );
    }
}
