<?php

use Inertia\Testing\AssertableInertia as Assert;

it('afișează pagina principală publică', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/home')->has('latestNews'));
});

it('randează paginile migrate cu secțiuni de conținut', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/page')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has('sections.0'));
})->with([
    'de ce columna' => '/de-ce-columna',
    'scrisoarea directorului' => '/scrisoarea-directorului',
    'filosofia liceului' => '/filosofia-liceului',
    'structura școlii' => '/structura-scolii',
    'acreditări' => '/acreditari',
    'contacte' => '/contacte',
    'școala primară' => '/scoala-primara',
    'școala gimnazială' => '/scoala-gimnaziala',
    'gimnaziu — curriculum' => '/scoala-gimnaziala/curriculum',
    'gimnaziu — dotări' => '/scoala-gimnaziala/dotari',
    'gimnaziu — galerie' => '/scoala-gimnaziala/galerie',
    'școala liceală' => '/scoala-liceala',
    'liceu — curriculum' => '/scoala-liceala/curriculum',
    'liceu — dotări' => '/scoala-liceala/dotari',
    'liceu — galerie' => '/scoala-liceala/galerie',
    'admitere' => '/admitere',
    'biblioteca online' => '/biblioteca-online',
    'CEI' => '/centrul-de-evaluare-institutionala',
    'sponsorizare' => '/sponsorizare',
    'tabără de vară' => '/tabara-de-vara',
    'cambridge' => '/cambridge-english-exam',
    'CPAE' => '/extracurriculare',
    'consiliul metodic' => '/consiliul-metodic',
    'consiliul școlar' => '/consiliul-scolar',
    'pregătire examene' => '/cursuri-de-pregatire-pentru-examene',
    'orarul lecțiilor' => '/orarul-lectiilor',
    'ședințe cu părinții' => '/sedintele-cu-parintii',
    'galerie' => '/galerie',
]);

it('afișează directorul de personal', function () {
    $this->get('/personal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/personal')->has('groups.0.members.0.name'));
});

it('afișează fișele individuale ale cadrelor (URL-uri vechi păstrate)', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/teacher')->where('name', fn ($n) => is_string($n) && $n !== ''));
})->with([
    'director' => '/danita-ghenadie',
    'profesor cu nume diferit de slug' => '/ciocoi-aliona',
    'fișă legacy' => '/radu-maria',
]);
