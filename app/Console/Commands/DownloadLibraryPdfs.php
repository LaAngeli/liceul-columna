<?php

namespace App\Console\Commands;

use App\Models\LibraryItem;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Descarcă local (în disk-ul `public`, folderul `downloads/biblioteca/`) toate PDF-urile bibliotecii
 * pointate la `columna.org.md`, apoi le repointează pe `LibraryItem::$file` (URL prin symlink
 * `public/storage`). După rulare, materialele NU mai depind de site-ul WordPress vechi — la
 * cutover-ul de domeniu (columna.md), link-urile continuă să funcționeze fără să atingem DB-ul.
 *
 * Idempotentă: dacă un item are deja fișier local valid (există fizic + mărime > 0), îl sare.
 * Sursă URL: coloana `link` (rămâne populată până la validare, apoi se golește pentru claritate).
 *
 * Notă cert SSL (Windows/Norton): dacă vine eroare de „revocation offline", relansează cu `--insecure`.
 */
class DownloadLibraryPdfs extends Command
{
    protected $signature = 'app:download-library-pdfs
        {--limit= : Descarcă maxim N materiale (test / rulare incrementală)}
        {--dry-run : Nu descarcă nimic; doar afișează ce ar face}
        {--insecure : Sare peste verificarea certificatelor SSL (fallback Windows)}';

    protected $description = 'Descarcă PDF-urile bibliotecii de pe columna.org.md în storage/app/public/downloads/biblioteca/ și repointează LibraryItem->file.';

    public function handle(): int
    {
        $items = LibraryItem::query()
            ->with('category')
            ->whereNotNull('link')
            ->where('link', 'like', '%columna.org.md%')
            ->orderBy('id')
            ->when($this->option('limit'), fn ($q, $limit) => $q->limit((int) $limit))
            ->get();

        if ($items->isEmpty()) {
            $this->info('Niciun material nu mai are link către columna.org.md — biblioteca e deja locală.');

            return self::SUCCESS;
        }

        $this->info("De procesat: {$items->count()} materiale.");
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        $client = Http::timeout(60)
            ->connectTimeout(15)
            ->when($this->option('insecure'), fn ($h) => $h->withOptions(['verify' => false]));

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            $result = $this->processItem($item, $client);
            if ($result['status'] === 'downloaded') {
                $downloaded++;
            } elseif ($result['status'] === 'skipped') {
                $skipped++;
            } else {
                $failed++;
                $failures[] = "{$item->id}: ".($result['reason'] ?? 'necunoscut');
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✔ Descărcate: {$downloaded}");
        $this->info("→ Sărite (deja locale): {$skipped}");

        if ($failed > 0) {
            $this->warn("✖ Eșuate: {$failed}");
            foreach ($failures as $f) {
                $this->line("   - {$f}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{status: 'downloaded'|'skipped'|'failed', reason?: string}
     */
    private function processItem(LibraryItem $item, PendingRequest $client): array
    {
        // URL DECODAT (WP livrează link-uri cu caractere non-URL în nume). Filename e ultima parte.
        $link = (string) $item->link;
        $urlPath = parse_url($link, PHP_URL_PATH) ?: '';
        $filename = rawurldecode(basename($urlPath));

        if ($filename === '' || ! str_ends_with(mb_strtolower($filename), '.pdf')) {
            return ['status' => 'failed', 'reason' => "URL fără .pdf: {$link}"];
        }

        // Categoria din URL structure: /biblioteca/{cat-slug}/{file.pdf}. Fallback = slug DB.
        $urlSegments = array_values(array_filter(explode('/', $urlPath), fn ($s) => $s !== ''));
        $catSlug = $urlSegments[1] ?? $item->category->slug ?? 'necategorizat';

        $relativePath = "downloads/biblioteca/{$catSlug}/{$filename}";
        $disk = Storage::disk('public');

        // Idempotent: file local există și e nevid → doar setează câmpul (dacă lipsește).
        if ($disk->exists($relativePath) && $disk->size($relativePath) > 0) {
            if ($item->file !== $relativePath && ! $this->option('dry-run')) {
                $item->update(['file' => $relativePath, 'link' => null]);
            }

            return ['status' => 'skipped'];
        }

        if ($this->option('dry-run')) {
            return ['status' => 'downloaded'];
        }

        try {
            $response = $client->get($link);
        } catch (ConnectionException $e) {
            return ['status' => 'failed', 'reason' => "HTTP {$link}: {$e->getMessage()}"];
        }

        if (! $response->successful()) {
            return ['status' => 'failed', 'reason' => "HTTP {$response->status()} pe {$link}"];
        }

        $body = $response->body();

        if ($body === '' || ! str_starts_with($body, '%PDF-')) {
            return ['status' => 'failed', 'reason' => "Răspuns non-PDF pe {$link} (start: ".mb_substr($body, 0, 10).')'];
        }

        $disk->put($relativePath, $body);
        $item->update(['file' => $relativePath, 'link' => null]);

        return ['status' => 'downloaded'];
    }
}
