<?php

namespace App\Models;

use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
