<?php

use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Filament\Content\Resources\Blog\Pages\EditBlogPost;
use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\EditLibraryCategory;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Models\LibraryCategory;
use App\Models\Post;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/**
 * Comportamentul cerut al câmpului de publicare, comun tuturor secțiunilor de conținut:
 *  - implicit (fără nicio interacțiune) → publicare AUTOMATĂ acum;
 *  - comutator explicit ON + dată aleasă → publicare/republicare la acea dată (fără oră);
 *  - comutator explicit ON + dată goală → rămâne ciornă;
 *  - la editare, dacă nu se interacționează cu comutatorul, data existentă NU se schimbă.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
    Storage::fake('public');
    // Fișierul-hero al articolelor din testele de editare TREBUIE să existe pe disk: FileUpload
    // elimină la hidratare o cale către un fișier inexistent, iar imaginea e acum obligatorie.
    Storage::disk('public')->put('posts/seed.webp', 'fake-image-bytes');
});

/** Imaginea principală e OBLIGATORIE la articole — un hero fals pentru formularul de creare. */
function heroImage(): UploadedFile
{
    return UploadedFile::fake()->image('hero.jpg', 1600, 900);
}

/**
 * Traducerile complete RU+EN (toate 3 câmpuri per limbă) — cerute de politica „traduceri complete
 * obligatorii" din Studio. Extras într-un helper ca să nu duplic aceleași linii în fiecare test.
 *
 * @return array<string, array<string, string>>
 */
function translations(): array
{
    return [
        'ru' => ['title' => 'Русский заголовок', 'slug' => 'russkij-zagolovok', 'excerpt' => 'Русское резюме.', 'content' => '<p>Русский текст.</p>'],
        'en' => ['title' => 'English title', 'slug' => 'english-title', 'excerpt' => 'English summary.', 'content' => '<p>English text.</p>'],
    ];
}

it('publică automat un articol nou (azi, la miezul nopții) fără nicio interacțiune cu data', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Articol publicat automat implicit',
            'slug' => 'articol-auto-acum',
            'excerpt' => 'Rezumat.',
            'content' => '<p>Conținut</p>',
            'image' => heroImage(),
            'translations' => translations(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->where('slug', 'articol-auto-acum')->firstOrFail();

    expect($post->published_at)->not->toBeNull()
        ->and($post->published_at->isToday())->toBeTrue()
        ->and($post->published_at->format('H:i:s'))->toBe('00:00:00');
});

it('permite alegerea explicită a unei alte date de publicare la creare', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Articol programat explicit',
            'slug' => 'articol-programat',
            'excerpt' => 'Rezumat.',
            'content' => '<p>Conținut</p>',
            'image' => heroImage(),
            'schedule_publish' => true,
            'published_at' => '2026-08-15',
            'translations' => translations(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->where('slug', 'articol-programat')->firstOrFail();

    expect($post->published_at->toDateString())->toBe('2026-08-15');
});

it('permite salvarea explicită ca ciornă la creare (comutator activ, dată goală)', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Articol lăsat ciornă',
            'slug' => 'articol-ciorna-explicit',
            'excerpt' => 'Rezumat.',
            'content' => '<p>Conținut</p>',
            'image' => heroImage(),
            'schedule_publish' => true,
            'published_at' => null,
            'translations' => translations(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->where('slug', 'articol-ciorna-explicit')->firstOrFail();

    expect($post->published_at)->toBeNull();
});

it('NU modifică data de publicare existentă la editare dacă nu se interacționează cu ea', function () {
    $original = now()->subDays(10)->startOfDay();
    $post = Post::factory()->create(['category' => 'blog', 'excerpt' => 'Rezumat scurt.', 'image' => 'posts/seed.webp', 'published_at' => $original]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Titlu corectat, fără să ating data', 'translations' => translations()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->published_at->equalTo($original))->toBeTrue();
});

it('permite reprogramarea explicită la editare', function () {
    $post = Post::factory()->create(['category' => 'blog', 'excerpt' => 'Rezumat scurt.', 'image' => 'posts/seed.webp', 'published_at' => now()->subDay()]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['schedule_publish' => true, 'published_at' => '2026-09-01', 'translations' => translations()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->published_at->toDateString())->toBe('2026-09-01');
});

it('păstrează ciorna la editare dacă nu se interacționează cu comutatorul', function () {
    $post = Post::factory()->create(['category' => 'blog', 'excerpt' => 'Rezumat scurt.', 'image' => 'posts/seed.webp', 'published_at' => null]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Tot ciornă rămân', 'translations' => translations()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->published_at)->toBeNull();
});

it('publică automat un album nou (azi, la miezul nopții) fără nicio interacțiune cu data', function () {
    Livewire::test(CreateGalleryAlbum::class)
        ->fillForm([
            'title' => 'Album auto',
            'translations' => ['ru' => ['title' => 'Альбом', 'slug' => 'albom'], 'en' => ['title' => 'Album', 'slug' => 'album']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $album = GalleryAlbum::query()->firstOrFail();

    expect($album->published_at)->not->toBeNull()
        ->and($album->published_at->format('H:i:s'))->toBe('00:00:00');
});

it('nu modifică data unui album existent la editare dacă nu se interacționează cu ea', function () {
    $original = now()->subDays(5)->startOfDay();
    $album = GalleryAlbum::factory()->create(['published_at' => $original]);

    Livewire::test(EditGalleryAlbum::class, ['record' => $album->getRouteKey()])
        ->fillForm([
            'title' => 'Titlu corectat',
            'translations' => ['ru' => ['title' => 'Альбом', 'slug' => 'albom'], 'en' => ['title' => 'Album', 'slug' => 'album']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($album->refresh()->published_at->equalTo($original))->toBeTrue();
});

it('publică automat o categorie de bibliotecă nouă (azi, la miezul nopții) fără nicio interacțiune', function () {
    Livewire::test(CreateLibraryCategory::class)
        ->fillForm([
            'title' => 'Categorie auto',
            'slug' => 'categorie-auto-acum',
            'kind' => 'documents',
            'translations' => ['ru' => ['title' => 'Категория', 'slug' => 'kategoriya'], 'en' => ['title' => 'Category', 'slug' => 'category']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = LibraryCategory::query()->where('slug', 'categorie-auto-acum')->firstOrFail();

    expect($category->published_at)->not->toBeNull()
        ->and($category->published_at->format('H:i:s'))->toBe('00:00:00');
});

it('nu modifică data unei categorii existente la editare dacă nu se interacționează cu ea', function () {
    $original = now()->subDays(3)->startOfDay();
    $category = LibraryCategory::factory()->create(['published_at' => $original]);

    Livewire::test(EditLibraryCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm([
            'title' => 'Titlu corectat',
            'translations' => ['ru' => ['title' => 'Категория', 'slug' => 'kategoriya'], 'en' => ['title' => 'Category', 'slug' => 'category']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->published_at->equalTo($original))->toBeTrue();
});

it('păstrează ordinea reală de creare pentru articolele publicate în aceeași zi (fără oră)', function () {
    $day = now()->startOfDay();
    $first = Post::factory()->create(['category' => 'actualitati', 'excerpt' => 'Primul.', 'published_at' => $day]);
    $second = Post::factory()->create(['category' => 'actualitati', 'excerpt' => 'Al doilea.', 'published_at' => $day]);

    $this->get('/actualitati-si-evenimente')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts', function ($posts) use ($first, $second) {
                $slugs = collect($posts)->pluck('slug')->values()->all();

                return array_search($second->slug, $slugs, true) < array_search($first->slug, $slugs, true);
            }));
});
