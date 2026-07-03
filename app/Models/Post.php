<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int|null $wp_id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $image
 * @property Carbon|null $published_at
 */
class Post extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'wp_id',
        'title',
        'slug',
        'category',
        'excerpt',
        'content',
        'image',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Rezolvare de rută prin oricare slug localizat: încercăm întâi RO (coloana proprie), apoi
     * traducerile RU/EN (slug pe post_translations). Așa articolul e accesibil pe URL-ul localizat
     * al oricărei limbi, indiferent de locale-ul curent.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $post = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();

        if ($post !== null) {
            return $post;
        }

        $translation = PostTranslation::query()->where('slug', $value)->first();

        return $translation?->post;
    }

    /**
     * @return HasMany<PostTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    /**
     * Traducerea pentru o limbă (sau cea curentă); null pentru RO/lipsă.
     */
    public function translation(?string $locale = null): ?PostTranslation
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ro') {
            return null;
        }

        return $this->translations->where('locale', $locale)->first();
    }

    public function localizedTitle(?string $locale = null): string
    {
        $translation = $this->translation($locale);
        $title = $translation === null ? $this->title : ($translation->title ?? $this->title);

        // Titlurile importate din WordPress pot conține HTML rezidual (ex. `<br>`) și entități —
        // un titlu nu e niciodată HTML, așa că îl curățăm pentru afișare (carduri, hero, breadcrumb).
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5)));
    }

    public function localizedExcerpt(?string $locale = null): ?string
    {
        $translation = $this->translation($locale);

        return $translation === null ? $this->excerpt : ($translation->excerpt ?? $this->excerpt);
    }

    public function localizedContent(?string $locale = null): string
    {
        $translation = $this->translation($locale);

        return $translation === null ? $this->content : ($translation->content ?? $this->content);
    }

    /**
     * URL utilizabil pentru `<img src>`: URL-urile absolute (import WP) și căile deja root-relative
     * trec neatinse; căile stocate pe disk (upload din Studio) sunt rezolvate prin storage.
     */
    public function imageUrl(): ?string
    {
        if ($this->image === null || $this->image === '') {
            return null;
        }

        if (str_starts_with($this->image, 'http://')
            || str_starts_with($this->image, 'https://')
            || str_starts_with($this->image, '/')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
}
