<?php

use App\Enums\LibraryKind;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Models\Admin;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Support\BibliotecaLibrary;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('afișează paginile bibliotecii pentru contul de Studio', function (string $url) {
    $this->actingAs(Admin::factory()->create(), 'admin')->get($url)->assertOk();
})->with(['/studio/biblioteca', '/studio/biblioteca/create']);

it('BibliotecaLibrary::categories() întoarce catalogul din DB în forma așteptată', function () {
    $cat = LibraryCategory::factory()->literature()->create([
        'slug' => 'literatura-romana',
        'title' => 'Literatura Română',
        'sort_order' => 1,
    ]);
    LibraryItem::factory()->for($cat, 'category')->create([
        'title' => 'Chirița în Iași',
        'author' => 'Alecsandri, Vasile',
        'link' => 'https://x/chirita.pdf',
    ]);

    LibraryCategory::factory()->draft()->create(['slug' => 'ascuns']);

    $categories = BibliotecaLibrary::categories();

    expect($categories)->toHaveCount(1)
        ->and($categories[0]['key'])->toBe('literatura-romana')
        ->and($categories[0]['title'])->toBe('Literatura Română')
        ->and($categories[0]['kind'])->toBe('literature')
        ->and($categories[0]['books'][0])->toBe([
            'title' => 'Chirița în Iași',
            'author' => 'Alecsandri, Vasile',
            'url' => 'https://x/chirita.pdf',
        ]);
});

it('localizează titlul categoriei din traducerile JSON', function () {
    $cat = LibraryCategory::factory()->create([
        'title' => 'Curriculum',
        'translations' => ['ru' => ['title' => 'Куррикулум'], 'en' => ['title' => 'Curriculum EN']],
    ]);

    expect($cat->localizedTitle('ro'))->toBe('Curriculum')
        ->and($cat->localizedTitle('ru'))->toBe('Куррикулум')
        ->and($cat->localizedTitle('en'))->toBe('Curriculum EN');
});

it('LibraryItem::url() preferă fișierul, altfel linkul', function () {
    $link = new LibraryItem(['link' => 'https://x/y.pdf']);
    $file = new LibraryItem(['file' => 'biblioteca/z.pdf']);

    expect($link->url())->toBe('https://x/y.pdf')
        ->and($file->url())->toContain('/storage/biblioteca/z.pdf');
});

it('creează o categorie cu traduceri prin binding JSON direct', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreateLibraryCategory::class)
        ->fillForm([
            'title' => 'Ghiduri metodologice',
            'slug' => 'ghiduri-metodologice',
            'kind' => LibraryKind::Documents->value,
            'translations' => [
                'ru' => ['title' => 'Руководства', 'slug' => 'rukovodstva'],
                'en' => ['title' => 'Guides', 'slug' => 'guides'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cat = LibraryCategory::query()->firstOrFail();

    expect($cat->slug)->toBe('ghiduri-metodologice')
        ->and($cat->kind)->toBe(LibraryKind::Documents)
        ->and($cat->localizedTitle('ru'))->toBe('Руководства');
});

it('comanda app:import-library populează catalogul legacy', function () {
    $this->artisan('app:import-library')->assertSuccessful();

    $literature = LibraryCategory::query()->where('slug', 'literatura-romana')->first();

    expect($literature)->not->toBeNull()
        ->and($literature->kind)->toBe(LibraryKind::Literature)
        ->and($literature->items()->count())->toBeGreaterThan(100);
});
