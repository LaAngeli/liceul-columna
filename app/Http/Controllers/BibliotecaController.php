<?php

namespace App\Http\Controllers;

use App\Support\BibliotecaLibrary;
use App\Support\ContentTranslator;
use Inertia\Inertia;
use Inertia\Response;

class BibliotecaController extends Controller
{
    /**
     * Biblioteca online — pagină interactivă dedicată (NU trece prin `public/page`).
     * Catalogul ({@see BibliotecaLibrary}) vine din DB (administrat în Studio), cu chei stabile +
     * `kind` (literature/documents), ca frontend-ul să poată căuta, filtra pe categorii și grupa
     * literatura pe index alfabetic. Titlurile materialelor rămân RO (nume proprii / denumiri PDF).
     */
    public function index(): Response
    {
        $categories = [];
        $total = 0;

        foreach (BibliotecaLibrary::categories() as $category) {
            $count = count($category['books']);
            $total += $count;

            $categories[] = [
                'key' => $category['key'],
                'title' => $category['title'],
                'kind' => $category['kind'],
                'count' => $count,
                'books' => $category['books'],
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
}
