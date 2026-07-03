<?php

namespace App\Filament\Content\Support;

use App\Actions\Cms\ProcessUploadedImage;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Uploader-ul de imagini de galerie, PARTAJAT de acțiunea „Adăugare imagine" din pagina de listă
 * și de acțiunea „Adaugă imagini" din RelationManager-ul albumului. Fiecare imagine e forțată la
 * aspect UNIFORM 3:2 (cover), ca miniaturile din grid-ul site-ului să apară identic — fără
 * trunchiere neintenționată și fără gap-uri. Editorul poate ajusta ZONA VIZIBILĂ în editorul de
 * imagine deschis la fiecare încărcare (nimic nu se pierde fără confirmare).
 */
class GalleryImageUpload
{
    public static function field(string $name = 'images'): FileUpload
    {
        $width = (int) config('cms.gallery.image.width', 1500);
        $height = (int) config('cms.gallery.image.height', 1000);
        $aspect = (string) config('cms.gallery.image.aspect', '3:2');

        /** @var list<string> $mimes */
        $mimes = config('cms.media.image_mimes', ['image/jpeg', 'image/png', 'image/webp']);
        $maxKb = (int) config('cms.media.image_max_kb', 6144);
        $disk = (string) config('cms.media.disk', 'public');

        return FileUpload::make($name)
            ->label('Imagini')
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->image()
            ->disk($disk)
            ->directory('gallery')
            ->visibility('public')
            ->acceptedFileTypes($mimes)
            ->maxSize($maxKb)
            // Editor disponibil pentru a alege manual cadrul (buton „Editează").
            ->imageEditor()
            ->imageEditorAspectRatios([$aspect])
            // Front-side (FilePond): decupare la cover pe dimensiunile țintă. NU folosim
            // `imageCropAspectRatio()`: acela ar adăuga o validare `dimensions:ratio` care respinge
            // imaginile care nu-s deja fix 3:2 — inutil, fiindcă serverul le re-încadrează oricum.
            ->imageResizeMode('cover')
            ->imageResizeTargetWidth((string) $width)
            ->imageResizeTargetHeight((string) $height)
            // Server-side: decupare la dimensiuni EXACTE + WebP, garanția finală a uniformității.
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ProcessUploadedImage::class)->cover($file->getRealPath(), 'gallery', $width, $height))
            ->required()
            ->helperText("Se încadrează automat la {$width}×{$height}px ({$aspect}) și se convertește în WebP. Poți alege manual cadrul cu „Editează”.");
    }

    /**
     * Creează înregistrări GalleryImage pentru căile date, adăugate după ultima imagine existentă.
     *
     * @param  list<string>  $paths
     */
    public static function store(GalleryAlbum $album, array $paths): int
    {
        $order = (int) $album->images()->max('sort_order');
        $created = 0;

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            $order++;
            $album->images()->create(['path' => $path, 'sort_order' => $order]);
            $created++;
        }

        return $created;
    }
}
