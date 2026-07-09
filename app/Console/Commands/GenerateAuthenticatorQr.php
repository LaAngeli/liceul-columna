<?php

namespace App\Console\Commands;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generează codurile QR STATICE pentru descărcarea aplicației de autentificare (Google
 * Authenticator), afișate în ghidul 2FA din cabinet. URL-urile magazinelor sunt publice și
 * practic imuabile → QR-uri statice (cost zero la runtime), regenerabile prin această comandă.
 * Modulele sunt navy de brand (#0f4d77) pe fundal alb — scanabile în orice temă a cabinetului.
 *
 * Sursa de adevăr a URL-urilor. Dacă se schimbă, actualizează AICI + linkurile din
 * `resources/js/components/cabinet/authenticator-app-guide.tsx`, apoi re-rulează comanda.
 */
#[Signature('app:generate-authenticator-qr')]
#[Description('Regenerează QR-urile statice de descărcare a aplicației de autentificare (Android + iOS)')]
class GenerateAuthenticatorQr extends Command
{
    /** @var array<string, string> */
    private const TARGETS = [
        'google-authenticator-android' => 'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2',
        'google-authenticator-ios' => 'https://apps.apple.com/md/app/google-authenticator/id388497605',
    ];

    public function handle(): int
    {
        $dir = public_path('images/authenticator');
        File::ensureDirectoryExists($dir);

        $renderer = new ImageRenderer(
            new RendererStyle(
                size: 240,
                margin: 2,
                fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 77, 119)),
            ),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);

        foreach (self::TARGETS as $name => $url) {
            File::put($dir.DIRECTORY_SEPARATOR.$name.'.svg', $writer->writeString($url));
            $this->info("✓ {$name}.svg");
        }

        $this->info('QR-uri generate în public/images/authenticator/.');

        return self::SUCCESS;
    }
}
