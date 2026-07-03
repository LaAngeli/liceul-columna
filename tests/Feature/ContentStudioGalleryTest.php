<?php

use App\Filament\Content\Resources\Gallery\GalleryAlbumResource;
use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Gallery\RelationManagers\ImagesRelationManager;
use App\Filament\Content\Support\GalleryImageUpload;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Support\GalleryAlbums;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('afișează paginile galeriei pentru contul de Studio', function (string $url) {
    $this->actingAs(Admin::factory()->create(), 'admin')->get($url)->assertOk();
})->with(['/studio/galerie', '/studio/galerie/create']);

it('afișează pagina de editare a albumului cu grila de imagini', function () {
    GalleryAlbum::factory()->withImages(['/images/galerie/general/a.jpg'])->create(['slug' => 'edit-me']);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/studio/galerie/edit-me/edit')
        ->assertOk();
});

it('randează grila de imagini (relation manager) și îi vede înregistrările', function () {
    $album = GalleryAlbum::factory()
        ->withImages(['/images/galerie/general/a.jpg', '/images/galerie/general/b.jpg'])
        ->create(['slug' => 'cu-grila']);

    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ImagesRelationManager::class, [
        'ownerRecord' => $album,
        'pageClass' => EditGalleryAlbum::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords($album->images);
});

it('GalleryAlbums::all() întoarce albumele publicate din DB în forma așteptată', function () {
    GalleryAlbum::factory()
        ->withImages(['/images/galerie/general/a.jpg', '/images/galerie/general/b.jpg'])
        ->create(['slug' => 'evenimente', 'title' => 'Evenimente', 'sort_order' => 1]);
    GalleryAlbum::factory()->draft()->withImages(['/x.jpg'])->create(['slug' => 'ciorna']);
    GalleryAlbum::factory()->create(['slug' => 'gol']); // fără imagini → exclus

    $all = GalleryAlbums::all();

    expect($all)->toHaveCount(1)
        ->and($all[0]['key'])->toBe('evenimente')
        ->and($all[0]['label'])->toBe('Evenimente')
        ->and($all[0]['count'])->toBe(2)
        ->and($all[0]['images'][0]['src'])->toBe('/images/galerie/general/a.jpg');
});

it('localizează titlul albumului din traducerile JSON', function () {
    $album = GalleryAlbum::factory()->create([
        'title' => 'Evenimente',
        'translations' => ['ru' => ['title' => 'События'], 'en' => ['title' => 'Events']],
    ]);

    expect($album->localizedTitle('ro'))->toBe('Evenimente')
        ->and($album->localizedTitle('ru'))->toBe('События')
        ->and($album->localizedTitle('en'))->toBe('Events');
});

it('imagesFor întoarce imaginile albumului după slug', function () {
    GalleryAlbum::factory()
        ->withImages(['/images/galerie/scoala-primara/1.jpg'])
        ->create(['slug' => 'scoala-primara']);

    $images = GalleryAlbums::imagesFor('scoala-primara');

    expect($images)->toHaveCount(1)
        ->and($images[0]['src'])->toBe('/images/galerie/scoala-primara/1.jpg');
});

it('creează un album minimal (doar titlu) cu slug + dată de publicare automate', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreateGalleryAlbum::class)
        ->fillForm([
            'title' => 'Album nou',
            'slug' => 'album-nou',
            'translations' => [
                'ru' => ['title' => 'Новый альбом', 'slug' => 'novyj-albom'],
                'en' => ['title' => 'New album', 'slug' => 'new-album'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $album = GalleryAlbum::query()->firstOrFail();

    expect($album->slug)->toBe('album-nou')
        ->and($album->published_at)->not->toBeNull()
        ->and($album->published_at->format('H:i:s'))->toBe('00:00:00')
        ->and($album->localizedTitle('ru'))->toBe('Новый альбом')
        ->and($album->localizedTitle('en'))->toBe('New album');
});

it('generează un slug unic când titlurile se repetă', function () {
    GalleryAlbum::factory()->create(['slug' => 'acelasi-titlu']);

    expect(GalleryAlbumResource::uniqueSlug('Același titlu'))->toBe('acelasi-titlu-2');
});

it('GalleryImageUpload::store adaugă imagini după cele existente, păstrând ordinea', function () {
    $album = GalleryAlbum::factory()->withImages(['a.webp'])->create();

    $created = GalleryImageUpload::store($album, ['b.webp', 'c.webp']);

    expect($created)->toBe(2)
        ->and($album->images()->orderBy('sort_order')->pluck('path')->all())
        ->toBe(['a.webp', 'b.webp', 'c.webp']);
});

it('url trece căile web și rezolvă căile stocate', function () {
    expect(GalleryAlbum::url('/images/galerie/x.jpg'))->toBe('/images/galerie/x.jpg')
        ->and(GalleryAlbum::url('https://x/y.jpg'))->toBe('https://x/y.jpg')
        ->and(GalleryAlbum::url('gallery/a.webp'))->toContain('/storage/gallery/a.webp');
});
