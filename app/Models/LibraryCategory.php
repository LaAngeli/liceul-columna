<?php

namespace App\Models;

use App\Enums\LibraryKind;
use Database\Factories\LibraryCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Categorie a Bibliotecii online. Traducerile RU/EN (doar titlul) stau în coloana JSON
 * `translations`, legate direct în formularul Filament.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property LibraryKind $kind
 * @property array<string, array<string, string|null>>|null $translations
 * @property int $sort_order
 * @property Carbon|null $published_at
 */
class LibraryCategory extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<LibraryCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'kind',
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
            'kind' => LibraryKind::class,
            'translations' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LibraryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LibraryItem::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<LibraryCategory>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<LibraryCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
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
}
