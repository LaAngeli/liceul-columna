<?php

use App\Actions\Cms\CheckContentHealth;
use App\Filament\Content\Widgets\ContentHealth;
use App\Models\Admin;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use App\Models\LibraryItem;
use App\Models\Post;
use App\Models\PostTranslation;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Monitorul de integritate DB ↔ fișiere (CheckContentHealth): detectează referințe rupte și
 * fișiere orfane apărute din modificări făcute în afara panoului (disk sau DB direct).
 */
beforeEach(function () {
    Storage::fake('public');
});

it('raportează curat când toate fișierele referențiate există', function () {
    Storage::disk('public')->put('posts/hero.webp', 'x');
    Post::factory()->blog()->create(['image' => 'posts/hero.webp', 'content' => '<p>Fără referințe de fișiere.</p>']);

    $report = app(CheckContentHealth::class)->run();

    expect($report['broken'])->toBe([])
        ->and($report['orphans'])->toBe([]);
});

it('detectează imaginea hero lipsă și fișierul de galerie lipsă', function () {
    Post::factory()->blog()->create(['image' => 'posts/lipsa.webp', 'content' => '<p>x</p>']);
    $album = GalleryAlbum::factory()->create();
    GalleryImage::factory()->create(['gallery_album_id' => $album->id, 'path' => 'gallery/lipsa.webp']);

    $report = app(CheckContentHealth::class)->run();

    expect($report['broken'])->toHaveCount(2)
        ->and(implode(' ', $report['broken']))
        ->toContain('imaginea principală lipsește')
        ->toContain('Imagine galerie');
});

it('detectează fișiere lipsă referențiate în conținut (RO și traduceri)', function () {
    $post = Post::factory()->blog()->create([
        'image' => null,
        'content' => '<p><img src="/storage/posts/din-corp.webp"></p>',
    ]);
    PostTranslation::factory()->create([
        'post_id' => $post->id,
        'locale' => 'ru',
        'content' => '<img src="/storage/posts/din-traducere.webp">',
    ]);

    $report = app(CheckContentHealth::class)->run();

    expect($report['broken'])->toHaveCount(2)
        ->and(implode(' ', $report['broken']))
        ->toContain('posts/din-corp.webp')
        ->toContain('posts/din-traducere.webp');
});

it('detectează materialul fără nicio sursă și pe cel cu fișierul lipsă', function () {
    LibraryItem::factory()->create(['file' => null, 'link' => null]);
    LibraryItem::factory()->create(['file' => 'downloads/biblioteca/lipsa.pdf', 'link' => null]);

    $report = app(CheckContentHealth::class)->run();

    expect($report['broken'])->toHaveCount(2)
        ->and(implode(' ', $report['broken']))
        ->toContain('nici fișier, nici link')
        ->toContain('fișierul lipsește');
});

it('detectează fișierele orfane, dar NU fișierele conținutului șters (restaurabil)', function () {
    Storage::disk('public')->put('posts/orfan.webp', 'x');

    // Fișierul unui articol ȘTERS rămâne referențiat — restaurarea trebuie să-l regăsească.
    Storage::disk('public')->put('posts/al-celui-sters.webp', 'x');
    Post::factory()->blog()->create(['image' => 'posts/al-celui-sters.webp', 'content' => '<p>x</p>'])->delete();

    $report = app(CheckContentHealth::class)->run();

    expect($report['broken'])->toBe([])
        ->and($report['orphans'])->toBe(['posts/orfan.webp']);
});

it('comanda app:content-health rulează și raportează', function () {
    Storage::disk('public')->put('posts/orfan.webp', 'x');
    Post::factory()->blog()->create(['image' => 'posts/lipsa.webp', 'content' => '<p>x</p>']);

    $this->artisan('app:content-health')
        ->expectsOutputToContain('Referințe rupte (1):')
        ->expectsOutputToContain('Fișiere orfane (1)')
        ->assertSuccessful();
});

it('widgetul de integritate se randează pe dashboard', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ContentHealth::class)
        ->assertOk()
        ->assertSee('Referințe rupte')
        ->assertSee('Fișiere orfane');
});
