<?php

namespace App\Console\Commands;

use App\Enums\LibraryKind;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Support\BibliotecaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportLibrary extends Command
{
    protected $signature = 'app:import-library {--force : Reimportă categoriile care există deja}';

    protected $description = 'Importă biblioteca legacy (BibliotecaLibrary::seedCatalog) în DB (materialele ca linkuri).';

    public function handle(): int
    {
        $sort = 0;

        foreach (BibliotecaLibrary::seedCatalog() as $category) {
            $sort++;
            $slug = self::slugFor($category['title']);

            if (LibraryCategory::query()->where('slug', $slug)->exists() && ! $this->option('force')) {
                $this->line("• {$slug}: există deja (--force pentru reimport) — sar peste.");

                continue;
            }

            $model = LibraryCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $category['title'],
                    'kind' => str_contains($category['title'], 'Literatura') ? LibraryKind::Literature : LibraryKind::Documents,
                    'sort_order' => $sort,
                    'published_at' => now(),
                ],
            );

            // Reimport curat: golim materialele existente și le recreăm în ordinea din catalog.
            $model->items()->delete();

            $position = 0;
            $usedSlugs = [];
            foreach ($category['books'] as $book) {
                $position++;
                $model->items()->create([
                    'title' => $book['title'],
                    'slug' => self::uniqueItemSlug($book['title'], $usedSlugs),
                    'link' => $book['url'],
                    'sort_order' => $position,
                ]);
            }

            $this->info("✓ {$slug}: ".count($category['books']).' materiale importate.');
        }

        return self::SUCCESS;
    }

    private static function slugFor(string $title): string
    {
        return match (true) {
            str_contains($title, 'Literatura') => 'literatura-romana',
            str_contains($title, 'Ghiduri') => 'ghiduri-2019',
            str_contains($title, '2023-2024') => 'repere-2023-2024',
            str_contains($title, '2022-2023') => 'repere-2022-2023',
            str_contains($title, '2010') => 'curriculum-2010',
            str_contains($title, '2019') => 'curriculum-2019',
            default => Str::slug($title),
        };
    }

    /**
     * Slug unic per import: dacă mai există unul identic în categoria curentă sau global în DB,
     * adăugăm sufix numeric.
     *
     * @param  array<string, bool>  $usedSlugs  scratch pentru sesiunea curentă (by ref)
     */
    private static function uniqueItemSlug(string $title, array &$usedSlugs): string
    {
        $base = Str::slug($title) ?: 'material';
        $slug = $base;
        $suffix = 1;

        while (isset($usedSlugs[$slug]) || LibraryItem::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        $usedSlugs[$slug] = true;

        return $slug;
    }
}
