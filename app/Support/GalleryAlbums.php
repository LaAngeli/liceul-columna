<?php

namespace App\Support;

/**
 * Albumele galeriei foto — scanate din `public/images/galerie/<folder>/`.
 * Sursă UNICĂ pentru pagina interactivă `/galerie` (toate albumele) ȘI pentru secțiunile
 * `gallery` din paginile de structură (un singur folder), via {@see imagesFor()}.
 */
final class GalleryAlbums
{
    /** Folder => etichetă RO (etichetele se traduc în frontend prin `gallery.album.<key>`). */
    private const ALBUMS = [
        'general' => 'Evenimente și activități',
        'scoala-primara' => 'Școala primară',
        'scoala-gimnaziala' => 'Școala gimnazială',
        'scoala-liceala' => 'Școala liceală',
    ];

    /**
     * @return list<array{key: string, label: string, count: int, images: list<array{src: string, alt: string}>}>
     */
    public static function all(): array
    {
        $albums = [];
        foreach (self::ALBUMS as $folder => $label) {
            $images = self::imagesFor($folder);
            if ($images === []) {
                continue;
            }
            $albums[] = [
                'key' => $folder,
                'label' => $label,
                'count' => count($images),
                'images' => $images,
            ];
        }

        return $albums;
    }

    /**
     * @return list<array{src: string, alt: string}>
     */
    public static function imagesFor(string $folder): array
    {
        $dir = public_path('images/galerie/'.$folder);
        $files = is_dir($dir) ? (glob($dir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []) : [];
        natsort($files);

        return array_values(array_map(
            fn (string $path): array => ['src' => '/images/galerie/'.$folder.'/'.basename($path), 'alt' => 'Liceul „Columna"'],
            $files,
        ));
    }
}
