<?php

use App\Models\GalleryAlbum;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Sincronizare site ↔ Studio pentru TOATE categoriile: ce e publicat în dashboard apare pe site,
 * ce e ciornă NU apare, iar mediile încărcate în Studio se rezolvă corect pe paginile publice.
 * Ambele capete citesc din aceeași sursă (fără cache de conținut), deci publicarea e imediată.
 */

// ---------------------------------------------------------------- Blog

it('sincronizează blogul: publicat pe /blog, ciorna ascunsă', function () {
    Post::factory()->create(['category' => 'blog', 'slug' => 'blog-publicat', 'published_at' => now()]);
    Post::factory()->create(['category' => 'blog', 'slug' => 'blog-ciorna', 'published_at' => null]);

    $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/articole/index')
            ->where('posts', fn ($posts) => collect($posts)->pluck('slug')->contains('blog-publicat')
                && collect($posts)->pluck('slug')->doesntContain('blog-ciorna')));
});

it('sincronizează pagina de articol: publicat 200, ciorna 404', function () {
    Post::factory()->create(['category' => 'blog', 'slug' => 'articol-viu', 'published_at' => now()]);
    Post::factory()->create(['category' => 'blog', 'slug' => 'articol-ciorna', 'published_at' => null]);

    $this->get('/articol/articol-viu')->assertOk();
    $this->get('/articol/articol-ciorna')->assertNotFound();
});

// ---------------------------------------------------------------- Actualități + Home

it('sincronizează actualitățile: publicate pe listă și pe home, ciornele nu', function () {
    Post::factory()->create(['category' => 'actualitati', 'slug' => 'stire-vie', 'published_at' => now()]);
    Post::factory()->create(['category' => 'actualitati', 'slug' => 'stire-ciorna', 'published_at' => null]);

    $this->get('/actualitati-si-evenimente')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts', fn ($posts) => collect($posts)->pluck('slug')->contains('stire-vie')
                && collect($posts)->pluck('slug')->doesntContain('stire-ciorna')));

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestNews', fn ($news) => collect($news)->pluck('slug')->contains('stire-vie')
                && collect($news)->pluck('slug')->doesntContain('stire-ciorna')));
});

it('rezolvă imaginile încărcate în Studio pe paginile publice (home + listă)', function () {
    Post::factory()->create([
        'category' => 'actualitati',
        'slug' => 'cu-imagine',
        'image' => 'posts/exemplu.webp',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestNews.0.image', fn ($image) => str_contains((string) $image, '/storage/posts/exemplu.webp')));

    $this->get('/actualitati-si-evenimente')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.0.image', fn ($image) => str_contains((string) $image, '/storage/posts/exemplu.webp')));
});

// ---------------------------------------------------------------- Galerie

it('sincronizează galeria: albumul publicat pe /galerie, ciorna ascunsă', function () {
    GalleryAlbum::factory()->withImages(['/images/galerie/general/a.jpg'])->create(['slug' => 'album-viu', 'published_at' => now()]);
    GalleryAlbum::factory()->draft()->withImages(['/images/galerie/general/b.jpg'])->create(['slug' => 'album-ciorna']);

    $this->get('/galerie')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('albums', fn ($albums) => collect($albums)->pluck('key')->contains('album-viu')
                && collect($albums)->pluck('key')->doesntContain('album-ciorna')));
});

// ---------------------------------------------------------------- Bibliotecă

it('sincronizează biblioteca: categoria publicată pe /biblioteca-online, ciorna ascunsă', function () {
    $live = LibraryCategory::factory()->create(['slug' => 'categorie-vie', 'published_at' => now()]);
    LibraryItem::factory()->for($live, 'category')->create();
    LibraryCategory::factory()->draft()->create(['slug' => 'categorie-ciorna']);

    $this->get('/biblioteca-online')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', fn ($categories) => collect($categories)->pluck('key')->contains('categorie-vie')
                && collect($categories)->pluck('key')->doesntContain('categorie-ciorna')));
});
