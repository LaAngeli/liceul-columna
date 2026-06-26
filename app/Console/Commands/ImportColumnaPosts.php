<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ImportColumnaPosts extends Command
{
    protected $signature = 'columna:import-posts {file : Calea către exportul WordPress (.xml)}';

    protected $description = 'Importă articolele (Actualități + Blog) din exportul WordPress columna.org.md';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("Fișierul nu există: {$file}");

            return self::FAILURE;
        }

        $xml = simplexml_load_file($file);

        if ($xml === false) {
            $this->error('Nu am putut citi exportul XML.');

            return self::FAILURE;
        }

        // Mapează id-ul atașamentului → URL (pentru imaginea reprezentativă).
        $attachments = [];
        foreach ($xml->channel->item as $item) {
            $wp = $item->children('wp', true);
            if ((string) $wp->post_type === 'attachment') {
                $attachments[(string) $wp->post_id] = (string) $wp->attachment_url;
            }
        }

        $count = 0;
        $usedSlugs = [];
        foreach ($xml->channel->item as $item) {
            $wp = $item->children('wp', true);

            if ((string) $wp->post_type !== 'post' || (string) $wp->status !== 'publish') {
                continue;
            }

            $content = (string) $item->children('content', true)->encoded;

            $category = 'actualitati';
            foreach ($item->category as $cat) {
                if ((string) $cat['domain'] === 'category' && stripos((string) $cat, 'blog') !== false) {
                    $category = 'blog';
                }
            }

            $image = null;
            foreach ($wp->postmeta as $meta) {
                if ((string) $meta->meta_key === '_thumbnail_id') {
                    $image = $attachments[(string) $meta->meta_value] ?? null;
                }
            }
            if ($image === null && preg_match('#https://columna\.org\.md/wp-content/uploads/[^"\' )]+\.(?:jpg|jpeg|png|webp)#i', $content, $match)) {
                $image = $match[0];
            }

            $excerpt = trim((string) $item->children('excerpt', true)->encoded);
            if ($excerpt === '') {
                $excerpt = Str::limit(trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content)))), 180);
            }

            $slug = (string) $wp->post_name;
            if ($slug === '') {
                $slug = Str::slug((string) $item->title) ?: 'articol-'.(int) $wp->post_id;
            }
            $base = $slug;
            $suffix = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }
            $usedSlugs[] = $slug;

            $publishedAt = $this->parseDate((string) $wp->post_date)
                ?? $this->parseDate((string) $wp->post_modified);

            Post::updateOrCreate(
                ['wp_id' => (int) $wp->post_id],
                [
                    'title' => trim((string) $item->title),
                    'slug' => $slug,
                    'category' => $category,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'image' => $image,
                    'published_at' => $publishedAt,
                ],
            );

            $count++;
        }

        $this->info("Importate/actualizate: {$count} articole.");

        return self::SUCCESS;
    }

    /**
     * Parsează o dată WordPress, ignorând valorile goale sau corupte
     * (ex. anul 0202 dintr-o eroare de introducere). Acceptă doar ani plauzibili.
     */
    private function parseDate(string $date): ?Carbon
    {
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return null;
        }

        try {
            $carbon = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        return ($carbon->year < 2000 || $carbon->year > 2100) ? null : $carbon;
    }
}
