<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Migrează articolele de pe vechiul domeniu WordPress (columna.org.md, cu sau fără „www") la noul
 * mediu columna.md, în două faze:
 *
 *  A. LOCALIZARE asset-uri — descarcă fișierele din `/wp-content/uploads/...` (imagini ȘI PDF-uri) în
 *     stocarea locală (disk `public`, sub `posts/imported/...`) și re-pointează la căi LOCALE:
 *     coloana `posts.image` → cale relativă pe disk (rezolvată de {@see Post::imageUrl()}), iar
 *     conținutul (`posts.content` + `post_translations.content`) → URL root-relative `/storage/...`,
 *     independent de domeniu. Site-ul devine self-contained (fără dependență de columna.org.md).
 *
 *  B. RE-POINTARE linkuri — orice URL columna.org.md rămas în conținut care NU e un asset (linkuri
 *     către PAGINI, ex. „/orarul-examenelor/") țintește o pagină ce va trăi pe columna.md → schimbă
 *     doar domeniul la columna.md (redirecturile 301 de la migrare acoperă restul).
 *
 * Idempotentă (a doua rulare nu mai găsește nimic) și rulabilă din nou după orice re-import.
 * ⚠️ columna.org.md trebuie să fie încă activ pentru faza A (de acolo se descarcă fișierele).
 */
class LocalizePostImages extends Command
{
    protected $signature = 'app:localize-post-images {--dry-run : Raportează fără a descărca sau modifica}';

    protected $description = 'Localizează asset-urile columna.org.md ale articolelor (imagini + PDF) și re-pointează linkurile de pagină la columna.md.';

    private const LEGACY_HOST = 'columna.org.md';

    private const UPLOADS_PREFIX = '/wp-content/uploads/';

    private const TARGET_DIR = 'posts/imported';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // ── Faza A: colectează asset-urile (uploads) din image + content (RO) + traduceri ──────
        /** @var array<string, string> $assets  URL legacy → cale disk relativă (posts/imported/...) */
        $assets = [];
        foreach ($this->assetSources() as $text) {
            foreach ($this->extractAssetUrls($text) as $url) {
                $rel = $this->localPathFor($url);
                if ($rel !== null) {
                    $assets[$url] = $rel;
                }
            }
        }

        // Descarcă fiecare fișier unic o singură dată.
        $disk = Storage::disk('public');
        /** @var array<string, true> $stored  căi locale disponibile pe disk (noi sau deja existente) */
        $stored = [];
        $downloaded = 0;
        $existing = 0;
        $failed = [];

        foreach (array_unique(array_values($assets)) as $rel) {
            if ($disk->exists($rel)) {
                $stored[$rel] = true;
                $existing++;

                continue;
            }

            if ($dry) {
                continue;
            }

            $sourceUrl = array_search($rel, $assets, true);
            $body = is_string($sourceUrl) ? $this->download($sourceUrl) : null;

            if ($body === null) {
                $failed[] = $rel;

                continue;
            }

            $disk->put($rel, $body);
            $stored[$rel] = true;
            $downloaded++;
        }

        // ── Rescrie DB (dacă nu e dry-run) ─────────────────────────────────────────────────────
        $imageRows = 0;
        $contentRows = 0;
        if (! $dry) {
            [$imageRows, $contentRows] = $this->rewrite($assets, $stored);
        }

        $this->table(
            ['asset-uri unice', 'descărcate', 'existente', 'eșuate', 'rânduri image', 'rânduri conținut'],
            [[count(array_unique(array_values($assets))), $downloaded, $existing, count($failed), $imageRows, $contentRows]],
        );

        if ($failed !== []) {
            $this->warn(count($failed).' fișiere nedescărcate (referințele lor rămân neatinse): '
                .implode(', ', array_slice($failed, 0, 8)).(count($failed) > 8 ? ' …' : ''));
        }

        if ($dry) {
            $this->comment('[dry-run] Nimic descărcat sau modificat.');
        }

        return self::SUCCESS;
    }

    /**
     * Textele care pot conține asset-uri legacy: imaginea hero + conținutul RO + traducerile.
     *
     * @return iterable<string>
     */
    private function assetSources(): iterable
    {
        foreach (Post::query()->whereNotNull('image')->where('image', 'like', '%'.self::LEGACY_HOST.'%')->pluck('image') as $image) {
            yield (string) $image;
        }

        foreach (Post::query()->where('content', 'like', '%'.self::LEGACY_HOST.'%')->pluck('content') as $content) {
            yield (string) $content;
        }

        foreach (PostTranslation::query()->where('content', 'like', '%'.self::LEGACY_HOST.'%')->pluck('content') as $content) {
            yield (string) $content;
        }
    }

    /**
     * URL-urile de asset (din `/wp-content/uploads/`) dintr-un text — orice scheme (http/https/
     * protocol-relative), cu sau fără „www", orice extensie (imagini ȘI PDF-uri).
     *
     * @return list<string>
     */
    private function extractAssetUrls(string $text): array
    {
        // Greedy până la delimitator (ghilimele/spațiu/paranteză), NU lazy până la prima „.ext" —
        // altfel numele cu puncte interne (`photo_...20.56.22.jpeg`, `25.pdf-3.png`) se trunchiază.
        $pattern = '#(?:https?:)?//(?:www\.)?'.preg_quote(self::LEGACY_HOST, '#')
            .preg_quote(self::UPLOADS_PREFIX, '#').'[^\s"\'<>)]+#i';
        preg_match_all($pattern, $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Calea locală relativă (posts/imported/AAAA/LL/nume.ext) pentru un URL de upload, sau null dacă
     * nu conține prefixul de uploads.
     */
    private function localPathFor(string $url): ?string
    {
        $pos = stripos($url, self::UPLOADS_PREFIX);
        if ($pos === false) {
            return null;
        }

        $sub = substr($url, $pos + strlen(self::UPLOADS_PREFIX));
        $sub = (string) preg_replace('/[?#].*$/', '', $sub);
        $sub = ltrim(rawurldecode($sub), '/');

        return $sub === '' ? null : self::TARGET_DIR.'/'.$sub;
    }

    /**
     * Descarcă corpul unui fișier de pe domeniul legacy (normalizat la https). Null la eșec.
     */
    private function download(string $url): ?string
    {
        $normalized = (string) preg_replace('#^(?:https?:)?//#i', 'https://', $url);

        // %-codează segmentele de cale (diacritice în numele de fișier) păstrând structura „/”.
        $parts = parse_url($normalized);
        if (is_array($parts) && isset($parts['host'], $parts['path'])) {
            $path = implode('/', array_map(
                static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
                explode('/', $parts['path']),
            ));
            $normalized = 'https://'.$parts['host'].$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        try {
            $response = Http::timeout(30)->retry(2, 1000)->get($normalized);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() && $response->body() !== '' ? $response->body() : null;
    }

    /**
     * Rescrie coloanele: `image` → cale relativă pe disk; `content` (RO + traduceri) → asset-urile
     * stocate devin `/storage/...`, iar linkurile de PAGINĂ rămase → columna.md.
     *
     * @param  array<string, string>  $assets  URL legacy → cale disk relativă
     * @param  array<string, true>  $stored  căi locale efectiv disponibile pe disk
     * @return array{0: int, 1: int} [rânduri image, rânduri conținut]
     */
    private function rewrite(array $assets, array $stored): array
    {
        // Doar asset-urile efectiv stocate se rescriu (altfel am rupe către un fișier lipsă).
        $storable = array_filter($assets, static fn (string $rel): bool => isset($stored[$rel]));

        $imageRows = 0;
        Post::query()->whereNotNull('image')->where('image', 'like', '%'.self::LEGACY_HOST.'%')->get()
            ->each(function (Post $post) use ($storable, &$imageRows): void {
                $urls = $this->extractAssetUrls((string) $post->image);
                if ($urls === [] || ! isset($storable[$urls[0]])) {
                    return;
                }
                $post->forceFill(['image' => $storable[$urls[0]]])->saveQuietly();
                $imageRows++;
            });

        $rewriteContent = function (string $html) use ($storable): string {
            foreach ($storable as $url => $rel) {
                $html = str_replace($url, '/storage/'.$rel, $html);
            }

            // Ce a rămas columna.org.md ȘI nu e un asset uploads = link de pagină → columna.md.
            return (string) preg_replace(
                '#(?:www\.)?'.preg_quote(self::LEGACY_HOST, '#').'(?!'.preg_quote(self::UPLOADS_PREFIX, '#').')#i',
                'columna.md',
                $html,
            );
        };

        $contentRows = 0;
        Post::query()->where('content', 'like', '%'.self::LEGACY_HOST.'%')->get()
            ->each(function (Post $post) use ($rewriteContent, &$contentRows): void {
                $post->forceFill(['content' => $rewriteContent((string) $post->content)])->saveQuietly();
                $contentRows++;
            });

        PostTranslation::query()->where('content', 'like', '%'.self::LEGACY_HOST.'%')->get()
            ->each(function (PostTranslation $translation) use ($rewriteContent, &$contentRows): void {
                $translation->forceFill(['content' => $rewriteContent((string) $translation->content)])->saveQuietly();
                $contentRows++;
            });

        return [$imageRows, $contentRows];
    }
}
