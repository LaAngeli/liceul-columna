<?php

namespace App\Actions\Cms;

use App\Models\GalleryImage;
use App\Models\LibraryItem;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Verifică integritatea conținutului /studio față de starea REALĂ de pe disk — plasa de siguranță
 * pentru modificările făcute în afara panoului (fișiere șterse/mutate manual, UPDATE-uri directe în
 * DB). Panoul și site-ul citesc aceeași bază de date (nu există copii de sincronizat); singurul
 * drift posibil e între DB și fișiere, și exact pe acela îl detectează verificarea:
 *
 *  - referințe RUPTE: rânduri active care arată către fișiere inexistente (imagine hero articol,
 *    imagini din corpul articolelor RO/RU/EN, imagini galerie, PDF-uri bibliotecă) sau materiale
 *    fără nicio sursă (nici fișier, nici link);
 *  - fișiere ORFANE: fișiere pe disk (posts/, gallery/, downloads/biblioteca/) nereferențiate de
 *    niciun rând — inclusiv cele șterse (soft delete), ca restaurarea să nu găsească fișierul lipsă.
 *
 * Rulată live de widgetul de pe dashboard și de comanda `app:content-health` — fără cache, ca
 * raportul să reflecte întotdeauna starea curentă.
 */
class CheckContentHealth
{
    /**
     * Rădăcinile de pe disk-ul public gestionate de Studio (scanate pentru orfani).
     */
    private const SCAN_ROOTS = ['posts', 'gallery', 'downloads/biblioteca'];

    /**
     * @return array{broken: list<string>, orphans: list<string>, external: int}
     */
    public function run(): array
    {
        $disk = Storage::disk((string) config('cms.media.disk', 'public'));

        $broken = [];
        $external = 0;

        // ── 1. Imaginile hero ale articolelor active ─────────────────────────────────────────
        foreach (Post::query()->whereNotNull('image')->get(['id', 'title', 'image']) as $post) {
            $image = (string) $post->image;

            if (str_starts_with($image, 'http')) {
                $external++;

                continue;
            }

            if (! $this->fileExists($disk, $image)) {
                $broken[] = 'Articol #'.$post->id.' („'.$this->shortTitle((string) $post->title).'"): imaginea principală lipsește ('.$image.')';
            }
        }

        // ── 2. Referințe /storage/... din corpul articolelor active (RO + traduceri) ─────────
        foreach (Post::query()->get(['id', 'title', 'content']) as $post) {
            foreach ($this->missingStorageRefs($disk, (string) $post->content) as $ref) {
                $broken[] = 'Articol #'.$post->id.' („'.$this->shortTitle((string) $post->title).'"): fișier lipsă în conținut (/storage/'.$ref.')';
            }
        }

        foreach (PostTranslation::query()->whereHas('post')->whereNotNull('content')->get(['id', 'post_id', 'locale', 'content']) as $translation) {
            foreach ($this->missingStorageRefs($disk, (string) $translation->content) as $ref) {
                $broken[] = 'Traducere '.strtoupper((string) $translation->locale).' a articolului #'.$translation->post_id.': fișier lipsă în conținut (/storage/'.$ref.')';
            }
        }

        // ── 3. Imaginile galeriei (albume active) ─────────────────────────────────────────────
        foreach (GalleryImage::query()->whereHas('album')->get(['id', 'gallery_album_id', 'path']) as $image) {
            $path = (string) $image->path;

            if (str_starts_with($path, 'http')) {
                $external++;

                continue;
            }

            if (! $this->fileExists($disk, $path)) {
                $broken[] = 'Imagine galerie #'.$image->id.' (album #'.$image->gallery_album_id.'): fișier lipsă ('.$path.')';
            }
        }

        // ── 4. Materialele bibliotecii (categorii active) ─────────────────────────────────────
        foreach (LibraryItem::query()->whereHas('category')->get(['id', 'title', 'file', 'link']) as $item) {
            $file = (string) $item->file;
            $link = (string) $item->link;

            if ($file === '' && $link === '') {
                $broken[] = 'Material #'.$item->id.' („'.$this->shortTitle((string) $item->title).'"): nu are nici fișier, nici link';

                continue;
            }

            if ($file !== '' && ! $disk->exists($file)) {
                $broken[] = 'Material #'.$item->id.' („'.$this->shortTitle((string) $item->title).'"): fișierul lipsește ('.$file.')';
            }
        }

        return [
            'broken' => $broken,
            'orphans' => $this->orphans($disk),
            'external' => $external,
        ];
    }

    /**
     * Fișierele de pe disk nereferențiate de niciun rând (inclusiv rândurile șterse soft — fișierul
     * lor trebuie să rămână pe disk ca restaurarea să fie completă).
     *
     * @return list<string>
     */
    private function orphans(Filesystem $disk): array
    {
        $referenced = $this->referencedPaths();

        $orphans = [];
        foreach (self::SCAN_ROOTS as $root) {
            foreach ($disk->allFiles($root) as $file) {
                if (str_starts_with(basename($file), '.')) {
                    continue;
                }

                if (! isset($referenced[$file]) && ! isset($referenced[rawurldecode($file)])) {
                    $orphans[] = $file;
                }
            }
        }

        return $orphans;
    }

    /**
     * Toate căile de disk referențiate de conținut — inclusiv de rândurile șterse (soft delete).
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $referenced = [];

        $add = function (string $path) use (&$referenced): void {
            if ($path === '' || str_starts_with($path, 'http') || str_starts_with($path, '/')) {
                return;
            }
            $referenced[$path] = true;
            $referenced[rawurldecode($path)] = true;
        };

        foreach (Post::withTrashed()->whereNotNull('image')->pluck('image') as $image) {
            $add((string) $image);
        }

        foreach (Post::withTrashed()->pluck('content') as $content) {
            foreach ($this->storageRefs((string) $content) as $ref) {
                $add($ref);
            }
        }

        foreach (PostTranslation::query()->whereNotNull('content')->pluck('content') as $content) {
            foreach ($this->storageRefs((string) $content) as $ref) {
                $add($ref);
            }
        }

        foreach (GalleryImage::query()->pluck('path') as $path) {
            $add((string) $path);
        }

        foreach (LibraryItem::query()->whereNotNull('file')->pluck('file') as $file) {
            $add((string) $file);
        }

        return $referenced;
    }

    /**
     * Căile relative pe disk din referințele `/storage/...` ale unui HTML.
     *
     * @return list<string>
     */
    private function storageRefs(string $html): array
    {
        preg_match_all('#/storage/([^\s"\'<>)?\#]+)#', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Referințele `/storage/...` dintr-un HTML al căror fișier NU există pe disk.
     *
     * @return list<string>
     */
    private function missingStorageRefs(Filesystem $disk, string $html): array
    {
        return array_values(array_filter(
            $this->storageRefs($html),
            fn (string $ref): bool => ! $disk->exists($ref) && ! $disk->exists(rawurldecode($ref)),
        ));
    }

    /**
     * Există fișierul? Căile care încep cu `/` sunt root-relative față de `public/` (importuri
     * statice), restul sunt relative la disk-ul media.
     */
    private function fileExists(Filesystem $disk, string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return is_file(public_path(ltrim(rawurldecode($path), '/')));
        }

        return $disk->exists($path) || $disk->exists(rawurldecode($path));
    }

    private function shortTitle(string $title): string
    {
        return mb_strimwidth($title, 0, 40, '…');
    }
}
