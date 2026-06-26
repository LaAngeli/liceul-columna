<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
class Post extends Model
{
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

        return $translation === null ? $this->title : ($translation->title ?? $this->title);
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
