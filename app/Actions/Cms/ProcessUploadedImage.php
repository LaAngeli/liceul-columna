<?php

namespace App\Actions\Cms;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Hardening server-side pentru imaginile de conținut (aprobat de user):
 *  - redimensionare la dimensiuni EXACTE prin `cover()` (umple W×H, decupează surplusul) → șablon uniform,
 *    indiferent de imaginea sursă;
 *  - re-encodare în WebP → elimină metadatele (EXIF) și orice payload ascuns într-un fișier „imagine";
 *  - nume randomizat (ULID) → fără coliziuni / preserveFilenames (risc RCE menționat în docs).
 *
 * Apelat din `FileUpload::saveUploadedFileUsing()`. Întoarce calea relativă pe disk (stocată în DB).
 */
class ProcessUploadedImage
{
    /**
     * Decupare la dimensiuni EXACTE (cover) — pentru articole (hero uniform 16:9). Sursă = cale fișier.
     */
    public function cover(string $sourcePath, string $directory, int $width, int $height): string
    {
        $image = $this->manager()->decode($sourcePath);
        $image->cover($width, $height);

        return $this->store($image, $directory);
    }

    /**
     * Redimensionare în interiorul unei cutii (fără decupare, fără upscale) — pentru galerie:
     * lightbox-ul arată imaginea întreagă, decuparea uniformă o face grid-ul în frontend.
     */
    public function scaleWithin(string $sourcePath, string $directory, int $maxWidth, int $maxHeight): string
    {
        $image = $this->manager()->decode($sourcePath);
        $image->scaleDown($maxWidth, $maxHeight);

        return $this->store($image, $directory);
    }

    private function manager(): ImageManager
    {
        return new ImageManager(new Driver);
    }

    /**
     * Re-encodează în WebP (elimină EXIF), stochează cu nume randomizat și întoarce calea relativă.
     */
    private function store(ImageInterface $image, string $directory): string
    {
        $quality = (int) config('cms.media.webp_quality', 82);
        $encoded = $image->encode(new WebpEncoder(quality: $quality, strip: true));

        $disk = (string) config('cms.media.disk', 'public');
        $path = trim($directory, '/').'/'.Str::ulid()->toBase32().'.webp';

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }
}
