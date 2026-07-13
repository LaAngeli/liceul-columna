<?php

namespace App\Models;

use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * O imagine dintr-un album de galerie. `path` = cale stocată pe disk-ul public (upload nou) sau
 * cale web root-relative (import). Ordinea în album = `sort_order`.
 *
 * @property int $id
 * @property int $gallery_album_id
 * @property string $path
 * @property int $sort_order
 */
class GalleryImage extends Model
{
    /** @use HasFactory<GalleryImageFactory> */
    use HasFactory;

    protected $fillable = [
        'gallery_album_id',
        'path',
        'sort_order',
    ];

    /**
     * La ștergerea unei imagini, șterge și fișierul WebP de pe disk — altfel ciclurile de adăugare/
     * ștergere lasă orfane în `gallery/`. DOAR căile pe disk (upload nou, ex. „gallery/xxx.webp");
     * URL-urile externe sau căile statice root-relative („/images/...") importate NU se ating.
     */
    protected static function booted(): void
    {
        static::deleting(function (GalleryImage $image): void {
            $path = (string) $image->path;

            if ($path !== '' && ! str_starts_with($path, 'http') && ! str_starts_with($path, '/')) {
                Storage::disk((string) config('cms.media.disk', 'public'))->delete($path);
            }
        });
    }

    /**
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
