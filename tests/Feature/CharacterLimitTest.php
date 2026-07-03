<?php

use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Filament\Content\Support\CharacterLimit;
use App\Models\Admin;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Limita de caractere pe câmpurile text din Studio (vezi {@see CharacterLimit}):
 *  - oprire fizică la tastare/lipire — atribut HTML nativ `maxlength`, forțat prin
 *    `extraInputAttributes()` (Filament îl suprimă implicit pt. câmpuri într-un `Tabs`, verificat
 *    aici să nu regreseze la un upgrade Filament viitor);
 *  - validarea server-side (`->maxLength()`) rămâne backstop-ul REAL — un client care ocolește
 *    atributul HTML (ex. `fillForm()` în test, sau manipulare directă a DOM-ului) tot nu poate salva.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('randează atributul HTML maxlength pe câmpurile text limitate, în ciuda faptului că sunt în Tabs', function () {
    $html = $this->get('/studio/blog/create')->assertOk()->getContent();

    expect($html)->toContain('id="form.title"')
        ->and($html)->toMatch('/id="form\.title"[^>]*maxlength="120"/')
        ->and($html)->toMatch('/id="form\.slug"[^>]*maxlength="160"/');
});

it('validarea server-side respinge un titlu peste limită chiar dacă atributul HTML e ocolit', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => str_repeat('a', 121),
            'slug' => 'titlu-prea-lung',
            'content' => '<p>Conținut</p>',
        ])
        ->call('create')
        ->assertHasFormErrors(['title' => 'max']);
});
