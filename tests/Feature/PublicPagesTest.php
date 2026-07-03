<?php

use App\Models\GalleryAlbum;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
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
    'gimnaziu — curriculum' => '/scoala-gimnaziala/curriculum',
    'gimnaziu — dotări' => '/scoala-gimnaziala/dotari',
    'gimnaziu — galerie' => '/scoala-gimnaziala/galerie',
    'liceu — curriculum' => '/scoala-liceala/curriculum',
    'liceu — dotări' => '/scoala-liceala/dotari',
    'liceu — galerie' => '/scoala-liceala/galerie',
    'pregătire examene' => '/cursuri-de-pregatire-pentru-examene',
    'orarul lecțiilor' => '/orarul-lectiilor',
    'ședințe cu părinții' => '/sedintele-cu-parintii',
]);

it('afișează biblioteca online interactivă cu catalogul structurat', function () {
    LibraryCategory::factory()
        ->literature()
        ->has(LibraryItem::factory()->count(101), 'items')
        ->create(['sort_order' => 1]);

    $this->get('/biblioteca-online')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/biblioteca-online')
            ->where('totalBooks', fn ($n) => is_int($n) && $n > 100)
            ->has('categories.0.books.0.url')
            ->where('categories.0.kind', 'literature'));
});

it('afișează pagina-fanion „De ce Columna" bespoke', function () {
    $this->get('/de-ce-columna')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/de-ce-columna'));
});

it('afișează scrisoarea directorului bespoke', function () {
    $this->get('/scrisoarea-directorului')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/scrisoarea-directorului'));
});

it('afișează filosofia liceului bespoke', function () {
    $this->get('/filosofia-liceului')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/filosofia-liceului'));
});

it('afișează pagina de acreditări bespoke', function () {
    $this->get('/acreditari')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/acreditari'));
});

it('afișează pagina CEI bespoke', function () {
    $this->get('/centrul-de-evaluare-institutionala')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/centrul-de-evaluare-institutionala'));
});

it('afișează pagina structura școlii bespoke', function () {
    $this->get('/structura-scolii')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/structura-scolii'));
});

it('afișează pagina școala primară bespoke', function () {
    $this->get('/scoala-primara')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/scoala-primara'));
});

it('afișează pagina școala liceală bespoke', function () {
    $this->get('/scoala-liceala')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/scoala-liceala'));
});

it('afișează pagina Cambridge English Exam bespoke', function () {
    $this->get('/cambridge-english-exam')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/cambridge-english-exam'));
});

it('afișează pagina tabăra de vară bespoke (placeholder onest)', function () {
    $this->get('/tabara-de-vara')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/tabara-de-vara'));
});

it('afișează pagina /admitere bespoke', function () {
    $this->get('/admitere')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/admitere'));
});

it('afișează pagina /taxe bespoke', function () {
    $this->get('/taxe')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/taxe'));
});

it('afișează pagina /intrebari-frecvente bespoke', function () {
    $this->get('/intrebari-frecvente')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/intrebari-frecvente'));
});

it('afișează pagina /sponsorizare bespoke', function () {
    $this->get('/sponsorizare')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/sponsorizare'));
});

it('afișează pagina școala gimnazială bespoke', function () {
    $this->get('/scoala-gimnaziala')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/scoala-gimnaziala'));
});

it('afișează istoria liceului bespoke (timeline)', function () {
    $this->get('/istorie')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/istorie'));
});

it('afișează consiliul metodic bespoke', function () {
    $this->get('/consiliul-metodic')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/consiliul-metodic'));
});

it('afișează consiliul școlar bespoke', function () {
    $this->get('/consiliul-scolar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/consiliul-scolar'));
});

it('afișează pagina extracurriculare (CPAE) bespoke', function () {
    $this->get('/extracurriculare')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/extracurriculare'));
});

it('afișează pagina de contacte bespoke', function () {
    $this->get('/contacte')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/contacte'));
});

it('afișează galeria foto interactivă cu albume', function () {
    GalleryAlbum::factory()
        ->withImages(['/images/galerie/general/a.jpg', '/images/galerie/general/b.jpg'])
        ->create(['slug' => 'general']);

    $this->get('/galerie')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/galerie')
            ->where('totalPhotos', fn ($n) => is_int($n) && $n > 0)
            ->has('albums.0.images.0.src'));
});

it('afișează calendarul interactiv cu cele 9 tipuri de orar', function () {
    $this->get('/calendar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/calendar')
            ->has('scheduleTypes', 9)
            ->where('scheduleTypes.0.key', 'orarul-lectiilor')
            ->where('scheduleTypes.0.i18n', 'lessons'));
});

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
