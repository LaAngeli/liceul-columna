<?php

namespace App\Http\Controllers;

use App\Support\PublicPageContent;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    /**
     * Pagină publică „schelet". Conținutul se rezolvă la momentul cererii (în funcție
     * de limba activă), nu la înregistrarea rutei.
     *
     * @param  list<array{title: string, href?: string}>  $breadcrumbs
     */
    public function show(string $page, string $pageTitle, array $breadcrumbs = [], ?string $description = null, bool $hasDownloads = false): Response
    {
        return Inertia::render('public/page', [
            'title' => $pageTitle,
            'breadcrumbs' => $breadcrumbs,
            'description' => $description,
            'hasDownloads' => $hasDownloads,
            'sections' => PublicPageContent::sections($page),
        ]);
    }
}
