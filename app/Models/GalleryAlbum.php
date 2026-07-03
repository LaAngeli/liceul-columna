<?php

namespace App\Models;

use Database\Factories\GalleryAlbumFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Album al galeriei foto. Titlul e denumirea afișată pe site; traducerile RU/EN ale titlului stau
 * în coloana JSON `translations` ({locale: {title}}). Imaginile sunt înregistrări {@see GalleryImage}
 * (relația {@see images()}) — administrate separat (adăugare, grilă cu ștergere/reordonare).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property array<string, array<string, string|null>>|null $translations
 * @property int $sort_order
 * @property Carbon|null $published_at
 * @property-read Collection<int, GalleryImage> $images
 */
class GalleryAlbum extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<GalleryAlbumFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'translations',
        'sort_order',
        'published_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GalleryImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<GalleryAlbum>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<GalleryAlbum>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale !== 'ro') {
            $value = $this->translations[$locale]['title'] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->title;
    }

    /**
     * Imaginile albumului ca intrări pentru frontend ({src, alt}). `src` rezolvă căile stocate
     * (upload nou) și trece URL-urile / căile root-relative (import) neatinse. `alt` = titlul localizat.
     *
     * @return list<array{src: string, alt: string}>
     */
    public function imageEntries(?string $locale = null): array
    {
        $alt = $this->localizedTitle($locale);

        $entries = [];
        foreach ($this->images as $image) {
            $entries[] = ['src' => self::url($image->path), 'alt' => $alt];
        }

        return $entries;
    }

    public static function url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
