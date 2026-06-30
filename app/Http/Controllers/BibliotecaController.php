<?php

namespace App\Http\Controllers;

use App\Support\BibliotecaLibrary;
use App\Support\ContentTranslator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BibliotecaController extends Controller
{
    /**
     * Biblioteca online — pagină interactivă dedicată (NU trece prin `public/page`).
     * Catalogul real ({@see BibliotecaLibrary}) e structurat pe categorii cu chei stabile +
     * `kind` (literature/documents), ca frontend-ul să poată căuta, filtra pe categorii și
     * grupa literatura pe index alfabetic. Titlurile cărților rămân RO (sunt nume proprii/PDF).
     */
    public function index(): Response
    {
        $categories = [];
        $total = 0;

        foreach (BibliotecaLibrary::categories() as $category) {
            $title = $category['title'];
            $count = count($category['books']);
            $total += $count;

            $categories[] = [
                'key' => self::categoryKey($title),
                'title' => $title,
                'kind' => str_contains($title, 'Literatura') ? 'literature' : 'documents',
                'count' => $count,
                'books' => array_map(
                    fn (array $book): array => ['title' => $book['title'], 'url' => $book['url']],
                    $category['books'],
                ),
            ];
        }

        return Inertia::render('public/biblioteca-online', [
            'title' => ContentTranslator::string('Biblioteca online'),
            'description' => ContentTranslator::string('Cărți, curricula și ghiduri în format electronic.'),
            'breadcrumbs' => [['title' => ContentTranslator::string('Biblioteca online')]],
            'categories' => $categories,
            'totalBooks' => $total,
        ]);
    }

    /** Cheie scurtă, stabilă, pe categorie (pentru filtre + ancore + etichete i18n). */
    private static function categoryKey(string $title): string
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
}
