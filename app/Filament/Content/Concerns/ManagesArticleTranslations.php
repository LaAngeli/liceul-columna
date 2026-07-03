<?php

namespace App\Filament\Content\Concerns;

use App\Actions\Cms\SanitizeHtml;
use App\Models\Post;

/**
 * Persistarea traducerilor RU/EN dintr-un formular de articol. RO trăiește pe `Post`; RU/EN în
 * `post_translations` (câmpuri nullable → fallback la RO). Folosit de paginile Create/Edit de articol.
 */
trait ManagesArticleTranslations
{
    /**
     * Desprinde sub-arborele `translations` din datele formularului (nu e atribut pe Post).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, array<string, string|null>>}
     */
    protected function splitTranslations(array $data): array
    {
        /** @var array<string, array<string, string|null>> $translations */
        $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
        unset($data['translations']);

        return [$data, $translations];
    }

    /**
     * Upsert per limbă; dacă toate câmpurile sunt goale, șterge traducerea (rămâne fallback RO).
     *
     * @param  array<string, array<string, string|null>>  $translations
     */
    protected function syncTranslations(Post $post, array $translations): void
    {
        $sanitizer = app(SanitizeHtml::class);

        foreach (['ru', 'en'] as $locale) {
            $row = $translations[$locale] ?? [];

            $title = $this->cleanString($row['title'] ?? null);
            $slug = $this->cleanString($row['slug'] ?? null);
            $excerpt = $this->cleanString($row['excerpt'] ?? null);
            // Sanitizează, apoi normalizează golul la null: un RichEditor gol trimite „<p></p>", pe
            // care purifier-ul îl reduce la „" — îl vrem null ca să funcționeze fallback-ul la RO.
            $content = $this->cleanString($sanitizer->handle($row['content'] ?? null));

            if (! filled($title) && ! filled($slug) && ! filled($excerpt) && ! filled($content)) {
                $post->translations()->where('locale', $locale)->delete();

                continue;
            }

            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content],
            );
        }
    }

    protected function cleanString(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
