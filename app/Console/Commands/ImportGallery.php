<?php

namespace App\Console\Commands;

use App\Actions\Cms\ProcessUploadedImage;
use App\Models\GalleryAlbum;
use Illuminate\Console\Command;

class ImportGallery extends Command
{
    protected $signature = 'app:import-gallery {--force : Reprocesează albumele care există deja}';

    protected $description = 'Importă galeria din public/images/galerie/* în DB (WebP uniform, pe disk-ul public).';

    /** Folder => titlu RO (identic cu vechea sursă filesystem). */
    private const ALBUMS = [
        'general' => 'Evenimente și activități',
        'scoala-primara' => 'Școala primară',
        'scoala-gimnaziala' => 'Școala gimnazială',
        'scoala-liceala' => 'Școala liceală',
    ];

    public function handle(ProcessUploadedImage $processor): int
    {
        $sort = 0;

        foreach (self::ALBUMS as $slug => $title) {
            $sort++;

            $existing = GalleryAlbum::query()->where('slug', $slug)->first();
            if ($existing !== null && ! $this->option('force')) {
                $this->line("• {$slug}: există deja (folosește --force pentru reimport) — sar peste.");

                continue;
            }

            $dir = public_path('images/galerie/'.$slug);
            $files = is_dir($dir) ? (glob($dir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []) : [];
            natsort($files);

            if ($files === []) {
                $this->line("• {$slug}: fără imagini — sar peste.");

                continue;
            }

            $album = GalleryAlbum::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'sort_order' => $sort,
                    'published_at' => now(),
                ],
            );

            // Reimport curat: golim imaginile existente și le recreăm în ordinea din folder.
            $album->images()->delete();

            $width = (int) config('cms.gallery.image.width', 1500);
            $height = (int) config('cms.gallery.image.height', 1000);

            $order = 0;
            foreach ($files as $file) {
                $order++;
                // Aspect uniform 3:2 pentru consistență cu grid-ul site-ului — imaginile
                // importate din surse eterogene sunt decupate central automat.
                $album->images()->create([
                    'path' => $processor->cover($file, 'gallery', $width, $height),
                    'sort_order' => $order,
                ]);
            }

            $this->info("✓ {$slug}: ".count($files).' imagini importate.');
        }

        return self::SUCCESS;
    }
}
