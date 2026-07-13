<?php

use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\EditLibraryCategory;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Models\LibraryCategory;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * G1 / L1: conținutul importat (albume/categorii cu translations = null) trebuie să fie EDITABIL cu
 * doar româna — traducerile RU/EN sunt obligatorii DOAR la creare (conținut nou), nu la editare.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('G1: editează un album importat (RU/EN goale) cu doar RO, fără erori', function () {
    $album = GalleryAlbum::factory()->create([
        'title' => 'Album importat', 'slug' => 'album-importat', 'translations' => null,
    ]);

    Livewire::test(EditGalleryAlbum::class, ['record' => $album->getRouteKey()])
        ->fillForm(['title' => 'Album importat corectat'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($album->refresh()->title)->toBe('Album importat corectat');
});

it('G1: crearea CERE în continuare titlurile RU/EN (complet trilingv)', function () {
    Livewire::test(CreateGalleryAlbum::class)
        ->fillForm(['title' => 'Doar română aici', 'slug' => 'doar-romana'])
        ->call('create')
        ->assertHasFormErrors(['translations.ru.title', 'translations.en.title']);
});

it('L1: editează o categorie importată (RU/EN goale) cu doar RO, fără erori', function () {
    $category = LibraryCategory::factory()->create([
        'title' => 'Categorie importată', 'slug' => 'categorie-importata', 'translations' => null,
    ]);

    Livewire::test(EditLibraryCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['title' => 'Categorie corectată'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->title)->toBe('Categorie corectată');
});

it('L1: crearea CERE în continuare titlurile RU/EN', function () {
    Livewire::test(CreateLibraryCategory::class)
        ->fillForm(['title' => 'Doar română aici', 'slug' => 'doar-romana', 'kind' => 'documents'])
        ->call('create')
        ->assertHasFormErrors(['translations.ru.title', 'translations.en.title']);
});
