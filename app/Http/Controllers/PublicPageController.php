<?php

namespace App\Http\Controllers;

use App\Support\ContentTranslator;
use App\Support\PublicPageContent;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    /**
     * Pagină publică „schelet". Conținutul se rezolvă la momentul cererii (în funcție
     * de limba activă), nu la înregistrarea rutei. Titlul, breadcrumb-urile și descrierea
     * (definite RO în rute) trec prin ContentTranslator, la fel ca secțiunile — altfel
     * rămâneau RO în orice limbă (cheile = șirul RO exact, în lang/{ru,en}/content.php).
     *
     * @param  list<array{title: string, href?: string}>  $breadcrumbs
     */
    public function show(string $page, string $pageTitle, array $breadcrumbs = [], ?string $description = null, bool $hasDownloads = false): Response
    {
        return Inertia::render('public/page', [
            'title' => ContentTranslator::string($pageTitle),
            'breadcrumbs' => array_map(
                fn (array $crumb): array => [...$crumb, 'title' => ContentTranslator::string($crumb['title'])],
                $breadcrumbs,
            ),
            'description' => $description !== null ? ContentTranslator::string($description) : null,
            'hasDownloads' => $hasDownloads,
            'sections' => PublicPageContent::sections($page),
        ]);
    }
}
