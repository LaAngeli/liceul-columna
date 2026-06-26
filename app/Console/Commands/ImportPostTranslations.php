<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Support\Locale;
use Illuminate\Console\Command;

/**
 * Importă traduceri de articole dintr-un fișier JSON (rânduri plate), upsert pe (post_id, locale).
 * Folosit pentru traducerea pe loturi (titluri/rezumate acum, corpuri ulterior).
 *
 * Format fișier: [{"post_id": int, "locale": "ru|en", "title"?: string, "excerpt"?: string, "content"?: string}, ...]
 * Câmpurile absente NU suprascriu valori existente (merge parțial — ex. adaugi corpul peste titlu).
 */
class ImportPostTranslations extends Command
{
    protected $signature = 'app:import-post-translations {file : Calea fișierului JSON cu traduceri}';

    protected $description = 'Importă/actualizează traducerile articolelor (post_translations) dintr-un fișier JSON.';

    public function handle(): int
    {
        /** @var string $file */
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Fișierul nu există: {$file}");

            return self::FAILURE;
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($file));
        $rows = json_decode((string) $raw, true);

        if (! is_array($rows)) {
            $this->error('JSON invalid.');

            return self::FAILURE;
        }

        $validPostIds = Post::query()->pluck('id')->all();
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $postId = $row['post_id'] ?? null;
            $locale = $row['locale'] ?? null;

            if (! is_int($postId) || ! in_array($postId, $validPostIds, true)
                || ! is_string($locale) || ! in_array($locale, Locale::prefixed(), true)) {
                $skipped++;

                continue;
            }

            $values = array_filter(
                [
                    'title' => $row['title'] ?? null,
                    'excerpt' => $row['excerpt'] ?? null,
                    'content' => $row['content'] ?? null,
                ],
                fn ($value): bool => is_string($value) && $value !== '',
            );

            if ($values === []) {
                $skipped++;

                continue;
            }

            PostTranslation::query()->updateOrCreate(
                ['post_id' => $postId, 'locale' => $locale],
                $values,
            );
            $imported++;
        }

        $this->info("Importate/actualizate: {$imported}. Ignorate: {$skipped}.");

        return self::SUCCESS;
    }
}
