<?php

namespace App\Http\Controllers;

use App\Support\ContentTranslator;
use App\Support\GalleryAlbums;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    /**
     * Galeria foto — pagină interactivă dedicată (NU mai trece prin `public/page`).
     * Albumele ({@see GalleryAlbums}) vin din folderele reale de imagini; frontend-ul le
     * filtrează și le deschide într-un lightbox (fără a părăsi pagina).
     */
    public function index(): Response
    {
        $albums = GalleryAlbums::all();
        $total = array_sum(array_map(static fn (array $album): int => $album['count'], $albums));

        return Inertia::render('public/galerie', [
            'title' => ContentTranslator::string('Galerie'),
            'description' => ContentTranslator::string('Momente din viața Liceului „Columna" — evenimente, activități și sărbători.'),
            'breadcrumbs' => [['title' => ContentTranslator::string('Galerie')]],
            'albums' => $albums,
            'totalPhotos' => $total,
        ]);
    }
}
