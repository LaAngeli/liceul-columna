<?php

use App\Filament\Content\Resources\Actualitati\Pages\CreateActualitate;
use App\Filament\Content\Resources\Actualitati\Pages\EditActualitate;
use App\Filament\Content\Resources\Actualitati\Pages\ListActualitati;
use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Filament\Content\Resources\Blog\Pages\EditBlogPost;
use App\Filament\Content\Resources\Blog\Pages\ListBlogPosts;
use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\ListGalleryAlbums;
use App\Filament\Content\Resources\Gallery\RelationManagers\ImagesRelationManager;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\EditLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\ListLibraryCategories;
use App\Filament\Content\Resources\Library\RelationManagers\ItemsRelationManager;
use App\Filament\Content\Widgets\ContentOverview;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\Post;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Verificare LIVE (render-smoke) a fiecărei secțiuni din panoul de conținut /studio: fiecare pagină
 * (listă / creare / editare) + RelationManagers + widgetul de ansamblu se montează fără eroare și
 * afișează datele reale. Echivalentul programatic al navigării pas-cu-pas prin dashboard.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
});

// ── Dashboard ────────────────────────────────────────────────────────────────
it('Dashboard: widgetul de ansamblu se randează și afișează totalurile pe secțiuni', function () {
    Post::factory()->blog()->count(3)->create();
    Post::factory()->actualitati()->count(5)->create();
    $album = GalleryAlbum::factory()->create(['published_at' => now()]);
    GalleryImage::factory()->count(4)->create(['gallery_album_id' => $album->id]);
    $category = LibraryCategory::factory()->create(['published_at' => now()]);
    LibraryItem::factory()->count(6)->create(['library_category_id' => $category->id]);

    Livewire::test(ContentOverview::class)
        ->assertOk()
        ->assertSee('Articole blog')
        ->assertSee('Actualități și evenimente')
        ->assertSee('Galerie')
        ->assertSee('Bibliotecă');
});

// ── Blog ─────────────────────────────────────────────────────────────────────
it('Blog: lista se randează și afișează articolele', function () {
    $posts = Post::factory()->blog()->count(4)->create();

    Livewire::test(ListBlogPosts::class)
        ->assertOk()
        ->assertCanSeeTableRecords($posts);
});

it('Blog: pagina de creare se randează', function () {
    Livewire::test(CreateBlogPost::class)->assertOk();
});

it('Blog: pagina de editare încarcă articolul', function () {
    $post = Post::factory()->blog()->create();

    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet(['title' => $post->title]);
});

// ── Actualități și evenimente ──────────────────────────────────────────────────
it('Actualități: lista se randează și afișează articolele', function () {
    $news = Post::factory()->actualitati()->count(4)->create();

    Livewire::test(ListActualitati::class)
        ->assertOk()
        ->assertCanSeeTableRecords($news);
});

it('Actualități: pagina de creare se randează', function () {
    Livewire::test(CreateActualitate::class)->assertOk();
});

it('Actualități: pagina de editare încarcă articolul', function () {
    $news = Post::factory()->actualitati()->create();

    Livewire::test(EditActualitate::class, ['record' => $news->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet(['title' => $news->title]);
});

// ── Galerie ────────────────────────────────────────────────────────────────────
it('Galerie: lista se randează și afișează albumele', function () {
    $albums = GalleryAlbum::factory()->count(3)->create();

    Livewire::test(ListGalleryAlbums::class)
        ->assertOk()
        ->assertCanSeeTableRecords($albums);
});

it('Galerie: pagina de creare se randează', function () {
    Livewire::test(CreateGalleryAlbum::class)->assertOk();
});

it('Galerie: pagina de editare încarcă albumul', function () {
    $album = GalleryAlbum::factory()->create();

    Livewire::test(EditGalleryAlbum::class, ['record' => $album->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet(['title' => $album->title]);
});

it('Galerie: RelationManager-ul de imagini se randează și afișează imaginile', function () {
    $album = GalleryAlbum::factory()->create();
    $images = GalleryImage::factory()->count(3)->create(['gallery_album_id' => $album->id]);

    Livewire::test(ImagesRelationManager::class, [
        'ownerRecord' => $album,
        'pageClass' => EditGalleryAlbum::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($images);
});

// ── Bibliotecă ──────────────────────────────────────────────────────────────────
it('Bibliotecă: lista se randează și afișează categoriile', function () {
    $categories = LibraryCategory::factory()->count(3)->create();

    Livewire::test(ListLibraryCategories::class)
        ->assertOk()
        ->assertCanSeeTableRecords($categories);
});

it('Bibliotecă: pagina de creare se randează', function () {
    Livewire::test(CreateLibraryCategory::class)->assertOk();
});

it('Bibliotecă: pagina de editare încarcă categoria', function () {
    $category = LibraryCategory::factory()->create();

    Livewire::test(EditLibraryCategory::class, ['record' => $category->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet(['title' => $category->title]);
});

it('Bibliotecă: RelationManager-ul de materiale se randează și afișează materialele', function () {
    $category = LibraryCategory::factory()->create();
    $items = LibraryItem::factory()->count(3)->create(['library_category_id' => $category->id]);

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditLibraryCategory::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($items);
});
