<?php

use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Models\Admin;
use App\Models\Post;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Remedieri pe stratul partajat de articole (Blog + Actualități), descoperite la verificarea live a
 * panoului /studio: embargo pe articole viitoare (B2), unicitate slug RU/EN (B1), rezumat RO obligatoriu
 * (B4), titluri scurte permise (B5).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
    Storage::fake('public');
});

/**
 * Formular de articol complet și valid. Selectoarele slug pe „manual" ca slug-urile explicite să fie
 * deterministe (auto ON le-ar rescrie din titlu).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validArticleForm(array $overrides = []): array
{
    return array_replace([
        'title' => 'Titlu suficient de lung',
        'slug' => 'titlu-suficient',
        'slug_auto_ro' => false,
        'slug_auto_ru' => false,
        'slug_auto_en' => false,
        'excerpt' => 'Rezumat RO.',
        'content' => '<p>Conținut RO valid și suficient.</p>',
        'image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
        'translations' => [
            'ru' => ['title' => 'Русский заголовок', 'slug' => 'ru-slug', 'excerpt' => 'Резюме.', 'content' => '<p>Текст.</p>'],
            'en' => ['title' => 'English title here', 'slug' => 'en-slug', 'excerpt' => 'Summary.', 'content' => '<p>Text.</p>'],
        ],
    ], $overrides);
}

it('B2: un articol programat în viitor dă 404 pe /articol/{slug}', function () {
    Post::factory()->create(['category' => 'blog', 'slug' => 'articol-viitor', 'published_at' => now()->addWeek()]);

    $this->get('/articol/articol-viitor')->assertNotFound();
});

it('B2: un articol publicat (dată trecută) dă 200', function () {
    Post::factory()->create(['category' => 'blog', 'slug' => 'articol-live', 'published_at' => now()->subDay()]);

    $this->get('/articol/articol-live')->assertOk();
});

it('B5: acceptă titluri scurte legitime (ex. „9 Mai")', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm(validArticleForm(['title' => '9 Mai', 'slug' => '9-mai']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Post::query()->where('slug', '9-mai')->exists())->toBeTrue();
});

it('B4: rezumatul RO e obligatoriu (auto OFF + gol → eroare)', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm(validArticleForm(['excerpt_auto_ro' => false]))
        // Golim rezumatul RO DUPĂ ce conținutul l-a putut deriva — cu auto OFF trebuie să rămână gol.
        ->fillForm(['excerpt' => ''])
        ->call('create')
        ->assertHasFormErrors(['excerpt']);
});

it('B1: un slug RU duplicat dă eroare de validare, nu 500', function () {
    $existing = Post::factory()->create(['category' => 'blog', 'slug' => 'primul']);
    $existing->translations()->create([
        'locale' => 'ru', 'title' => 'Новости', 'slug' => 'novosti', 'excerpt' => 'x', 'content' => '<p>x</p>',
    ]);

    Livewire::test(CreateBlogPost::class)
        ->fillForm(validArticleForm([
            'slug' => 'al-doilea',
            'translations' => [
                'ru' => ['title' => 'Другие новости', 'slug' => 'novosti', 'excerpt' => 'Рез.', 'content' => '<p>Текст.</p>'],
                'en' => ['title' => 'Other news here', 'slug' => 'other-en', 'excerpt' => 'Exc.', 'content' => '<p>Text.</p>'],
            ],
        ]))
        ->call('create')
        ->assertHasFormErrors(['translations.ru.slug']);
});
