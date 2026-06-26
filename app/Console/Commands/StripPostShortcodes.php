<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Curăță shortcode-urile WPBakery/builder rămase brute (`[vc_row ...]`, `[vc_column_text]`,
 * `[image_with_animation ...]` etc.) din `content`/`excerpt`-ul articolelor migrate din WP —
 * acelea se afișau literal pe pagină. Re-împachetează apoi paragrafele de text simplu în `<p>`
 * (conținutul WPBakery folosește `\n` între paragrafe, nu `<p>`).
 */
class StripPostShortcodes extends Command
{
    protected $signature = 'app:strip-post-shortcodes {--dry-run : Doar raportează + scrie previzualizări în storage/app/clean, fără a modifica}';

    protected $description = 'Curăță shortcode-urile builder rămase din articole (content/excerpt) și normalizează paragrafele.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Filtru grosier la nivel DB (orice `[`), rafinat în PHP cu regexul de shortcode.
        $posts = Post::query()
            ->where(function (Builder $query): void {
                $query->where('content', 'like', '%[%')->orWhere('excerpt', 'like', '%[%');
            })
            ->get()
            ->filter(fn (Post $post): bool => $this->hasShortcode((string) $post->content) || $this->hasShortcode((string) $post->excerpt))
            ->values();

        $translations = PostTranslation::query()
            ->where(function (Builder $query): void {
                $query->where('content', 'like', '%[%')->orWhere('excerpt', 'like', '%[%');
            })
            ->get()
            ->filter(fn (PostTranslation $tr): bool => $this->hasShortcode((string) $tr->content) || $this->hasShortcode((string) $tr->excerpt))
            ->values();

        if ($posts->isEmpty() && $translations->isEmpty()) {
            $this->info('Niciun articol cu shortcode-uri rămase.');

            return self::SUCCESS;
        }

        if ($dry) {
            @mkdir(storage_path('app/clean'), 0775, true);
        }

        $rows = [];
        foreach ($posts as $post) {
            $content = $this->clean((string) $post->content);
            $excerpt = $this->cleanExcerpt((string) $post->excerpt, $content);

            $rows[] = [
                $post->id,
                mb_strimwidth((string) $post->title, 0, 38, '…'),
                strlen((string) $post->content).' → '.strlen($content),
                substr_count($content, '['),
            ];

            if ($dry) {
                file_put_contents(storage_path("app/clean/{$post->id}.html"), $content);

                continue;
            }

            $post->update(['content' => $content, 'excerpt' => $excerpt]);
        }

        // Traduceri (RU/EN): curăță content-ul (shortcode-uri + paragrafe) + excerpt-ul (gol dacă rămâne garbage).
        foreach ($translations as $translation) {
            $content = $translation->content !== null ? $this->clean((string) $translation->content) : null;
            $stripped = trim((string) preg_replace('/\[\/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', '', (string) $translation->excerpt));
            $excerpt = ($stripped === '' || str_contains($stripped, '[')) ? null : $stripped;

            if (! $dry) {
                $translation->update(['content' => $content, 'excerpt' => $excerpt]);
            }
        }

        $this->table(['id', 'titlu', 'content (len)', 'rest ['], $rows);
        $this->info(($dry ? '[dry-run] ' : '').$posts->count().' articole + '.$translations->count().' traduceri '.($dry ? 'ar fi curățate' : 'curățate').'.');

        return self::SUCCESS;
    }

    /**
     * Conține deschiderea unui shortcode `[tag…` / `[/tag` (WPBakery, gallery, caption etc.),
     * complet sau TRUNCHIAT (excerpt-urile WP taie shortcode-ul la mijloc, fără `]`)?
     * Numele de 2+ caractere evită falsele potriviri pe `[i]`/`[1]` din proză.
     */
    private function hasShortcode(string $html): bool
    {
        return preg_match('/\[\/?[a-z][a-z0-9_-]+/i', $html) === 1;
    }

    /**
     * Elimină shortcode-urile builder și normalizează paragrafele de text simplu.
     */
    private function clean(string $html): string
    {
        // 1. Scoate delimitatorii de bloc Gutenberg (comentarii `<!-- wp:... -->`).
        $html = (string) preg_replace('/<!--\s*\/?wp:.*?-->/is', '', $html);
        // 2. Scoate toate shortcode-urile `[tag ...]` / `[/tag]`.
        $html = (string) preg_replace('/\[\/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', '', $html);
        // 3. Curăță spațiile de la capăt de linie + liniile goale multiple.
        $html = (string) preg_replace('/[ \t]+\n/', "\n", $html);
        $html = (string) preg_replace('/\n{2,}/', "\n", $html);

        $html = $this->paragraphize(trim($html));

        // 4. Elimină paragrafele rămase goale (ex. dintr-un bloc Gutenberg gol).
        $html = (string) preg_replace('/<p>\s*(?:&nbsp;)?\s*<\/p>/i', '', $html);
        $html = (string) preg_replace('/\n{2,}/', "\n", $html);

        return trim($html);
    }

    /**
     * Re-împachetează liniile de text simplu (din afara blocurilor) în `<p>`, păstrând
     * blocurile HTML existente (`<p>`, `<ul>`, `<ol>`, `<h*>`, `<blockquote>`, `<img>`, …).
     */
    private function paragraphize(string $html): string
    {
        $blockOpen = '/^<(p|ul|ol|h[1-6]|blockquote|div|figure)\b/i';
        $blockSelfContained = '/^<(li|img|table|hr|iframe)\b/i';
        $blockClose = '/<\/(p|ul|ol|h[1-6]|blockquote|div|figure)>\s*$/i';

        $out = [];
        $open = false;

        foreach (explode("\n", $html) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if ($open) {
                $out[count($out) - 1] .= "\n".$line;
                if (preg_match($blockClose, $trimmed)) {
                    $open = false;
                }

                continue;
            }

            if (preg_match($blockOpen, $trimmed)) {
                $out[] = $line;
                if (! preg_match($blockClose, $trimmed)) {
                    $open = true;
                }

                continue;
            }

            if (preg_match($blockSelfContained, $trimmed)) {
                $out[] = $line;

                continue;
            }

            $out[] = '<p>'.$trimmed.'</p>';
        }

        return implode("\n", $out);
    }

    /**
     * Curăță excerptul; dacă rămâne gol, îl regenerează din conținutul curat (text simplu).
     */
    private function cleanExcerpt(string $excerpt, string $cleanContent): string
    {
        $excerpt = trim((string) preg_replace('/\[\/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', '', $excerpt));

        // Gol SAU cu fragmente de shortcode trunchiat (`[vc_row type="in...` fără `]`) → regenerează din conținut.
        if ($excerpt === '' || str_contains($excerpt, '[')) {
            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($cleanContent)));
            $excerpt = mb_substr($text, 0, 200);
            if (mb_strlen($text) > 200) {
                $excerpt .= '…';
            }
        }

        return $excerpt;
    }
}
