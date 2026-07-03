<?php

namespace App\Models;

use Database\Factories\LibraryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Material din Bibliotecă: fie fișier PDF încărcat (`file`, pe disk-ul public), fie link extern
 * (`link`). {@see url()} întoarce sursa efectivă. Titlurile rămân RO (nume proprii / denumiri PDF).
 *
 * @property int $id
 * @property int $library_category_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $author
 * @property string|null $file
 * @property string|null $link
 * @property int $sort_order
 */
class LibraryItem extends Model
{
    /** @use HasFactory<LibraryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'library_category_id',
        'title',
        'slug',
        'author',
        'file',
        'link',
        'sort_order',
    ];

    /**
     * @return BelongsTo<LibraryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'library_category_id');
    }

    /**
     * Sursa descărcabilă: fișierul încărcat (prioritar) sau linkul extern; '' dacă lipsesc ambele.
     */
    public function url(): string
    {
        if (is_string($this->file) && $this->file !== '') {
            return Storage::disk('public')->url($this->file);
        }

        return $this->link ?? '';
    }
}
