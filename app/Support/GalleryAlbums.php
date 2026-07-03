<?php

namespace App\Support;

use App\Models\GalleryAlbum;

/**
 * Albumele galeriei foto — administrate în Studio ({@see GalleryAlbum}), citite din DB.
 * Sursă UNICĂ pentru pagina interactivă `/galerie` (toate albumele) ȘI pentru secțiunile
 * `gallery` din paginile de structură (un singur album), via {@see imagesFor()}.
 *
 * API-ul (contractul cu frontend-ul) e păstrat identic cu varianta pe filesystem.
 */
final class GalleryAlbums
{
    /**
     * @return list<array{key: string, label: string, count: int, images: list<array{src: string, alt: string}>}>
     */
    public static function all(): array
    {
        $albums = [];

        foreach (GalleryAlbum::query()->published()->ordered()->with('images')->get() as $album) {
            if ($album->images->isEmpty()) {
                continue;
            }

            $albums[] = [
                'key' => $album->slug,
                'label' => $album->localizedTitle(),
                'count' => $album->images->count(),
                'images' => $album->imageEntries(),
            ];
        }

        return $albums;
    }

    /**
     * @return list<array{src: string, alt: string}>
     */
    public static function imagesFor(string $folder): array
    {
        return GalleryAlbum::query()->where('slug', $folder)->with('images')->first()?->imageEntries() ?? [];
    }
}
