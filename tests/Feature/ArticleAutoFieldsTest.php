<?php

use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Models\Admin;
use App\Models\Post;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Selectoarele „automat/manual" din formularul de articol (Blog + Actualități) și acceptarea
 * imaginilor indiferent de raportul de aspect (server-ul le re-încadrează la 16:9).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('derivează automat slug-ul din titlu la creare (selector ON implicit)', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm(['title' => 'Un titlu suficient de lung pentru slug'])
        ->assertFormSet(['slug' => 'un-titlu-suficient-de-lung-pentru-slug']);
});

it('nu mai regenerează slug-ul când selectorul e pe manual', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'slug_auto_ro' => false,
            'slug' => 'slug-scris-de-mana',
            'title' => 'Un alt titlu complet diferit de slug',
        ])
        ->assertFormSet(['slug' => 'slug-scris-de-mana']);
});

it('derivează automat rezumatul din conținut la creare (selector ON implicit)', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm(['content' => '<p>Prima frază a articolului. A doua frază.</p>'])
        ->assertFormSet(['excerpt' => 'Prima frază a articolului. A doua frază.']);
});

it('taie rezumatul derivat la limită, păstrând cuvinte întregi', function () {
    $long = '<p>'.str_repeat('cuvant ', 60).'</p>'; // ~420 caractere, mult peste 200

    Livewire::test(CreateBlogPost::class)
        ->fillForm(['content' => $long])
        ->assertFormSet(function (array $state): bool {
            $excerpt = (string) ($state['excerpt'] ?? '');

            return mb_strlen($excerpt) <= 200 && str_ends_with($excerpt, '…');
        });
});

it('nu mai atinge rezumatul când selectorul e pe manual', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'excerpt_auto_ro' => false,
            'excerpt' => 'Rezumat scris manual.',
            'content' => '<p>Conținut complet diferit de rezumat.</p>',
        ])
        ->assertFormSet(['excerpt' => 'Rezumat scris manual.']);
});

it('acceptă o imagine care nu e 16:9 și o normalizează la WebP (fără respingere pe dimensiuni)', function () {
    Storage::fake('public');

    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Articol cu imagine pătrată de test',
            'slug' => 'articol-imagine-patrata',
            'excerpt_auto_ro' => false,
            'excerpt' => 'Rezumat manual.',
            'content' => '<p>Conținut suficient de lung.</p>',
            'image' => UploadedFile::fake()->image('patrat.jpg', 800, 800), // 1:1, NU 16:9
            'translations' => [
                'ru' => ['title' => 'Русский заголовок тест', 'slug' => 'ru-slug', 'excerpt' => 'Рез.', 'content' => '<p>Текст.</p>'],
                'en' => ['title' => 'English title for test', 'slug' => 'en-slug', 'excerpt' => 'Exc.', 'content' => '<p>Text.</p>'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->firstOrFail();

    expect($post->image)->not->toBeNull()
        ->and($post->image)->toEndWith('.webp');
});
