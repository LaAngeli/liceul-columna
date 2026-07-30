<?php

use App\Filament\Content\Resources\Blog\Pages\EditBlogPost;
use App\Filament\Content\Resources\Blog\Pages\ListBlogPosts;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Library\Pages\EditLibraryCategory;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\Post;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Restaurarea stă pe rândul butoanelor formularului ({@see PlacesRecordActionsWithForm}) —
 * se adresează în teste prin componenta de schemă `form-actions`.
 */
function restoreFormAction(): TestAction
{
    return TestAction::make('restore')->schemaComponent('form-actions', schema: 'content');
}

/**
 * Coșul /studio: conținutul șters (soft delete) dispare de pe site, rămâne vizibil în panou prin
 * filtrul „Înregistrări șterse" și poate fi restaurat — gestionare completă a conținutului
 * existent, nu doar adăugare.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('Blog: articolul șters dispare de pe site, apare în coș și restaurarea îl readuce', function () {
    $post = Post::factory()->blog()->create(['slug' => 'de-restaurat', 'published_at' => now()->subDay()]);
    $this->get('/articol/de-restaurat')->assertOk();

    $post->delete();
    $this->get('/articol/de-restaurat')->assertNotFound();

    // Lista implicită nu-l arată; filtrul „Doar șterse" da.
    Livewire::test(ListBlogPosts::class)
        ->assertCanNotSeeTableRecords([$post])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$post]);

    // Pagina de editare rezolvă și înregistrări șterse; „Restaurează" îl readuce pe site.
    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->callAction(restoreFormAction());

    expect($post->refresh()->trashed())->toBeFalse();
    $this->get('/articol/de-restaurat')->assertOk();
});

it('Galerie: albumul șters se restaurează cu imaginile lui intacte', function () {
    $album = GalleryAlbum::factory()->create(['slug' => 'album-de-restaurat', 'published_at' => now()]);
    GalleryImage::factory()->count(2)->create(['gallery_album_id' => $album->id]);

    $album->delete();

    // Rândurile de imagine rămân legate în DB cât timp albumul e în coș.
    expect(GalleryImage::query()->where('gallery_album_id', $album->id)->count())->toBe(2);

    Livewire::test(EditGalleryAlbum::class, ['record' => $album->getRouteKey()])
        ->callAction(restoreFormAction());

    $album->refresh();
    expect($album->trashed())->toBeFalse()
        ->and($album->images()->count())->toBe(2);
});

it('Bibliotecă: categoria ștearsă se restaurează cu materialele ei', function () {
    $category = LibraryCategory::factory()->create(['slug' => 'categorie-de-restaurat']);
    LibraryItem::factory()->count(3)->create(['library_category_id' => $category->id]);

    $category->delete();

    Livewire::test(EditLibraryCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(restoreFormAction());

    $category->refresh();
    expect($category->trashed())->toBeFalse()
        ->and($category->items()->count())->toBe(3);
});
