<?php

use App\Actions\Cms\SanitizeHtml;
use App\Filament\Content\Resources\Actualitati\ActualitatiResource;
use App\Filament\Content\Resources\Actualitati\Pages\CreateActualitate;
use App\Filament\Content\Resources\Blog\BlogResource;
use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Models\Admin;
use App\Models\Post;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('afișează paginile de conținut pentru contul de Studio', function (string $url) {
    $this->actingAs(Admin::factory()->create(), 'admin')->get($url)->assertOk();
})->with([
    '/studio/blog',
    '/studio/blog/create',
    '/studio/actualitati',
    '/studio/actualitati/create',
]);

it('nu oferă „creează încă unul" pe paginile de creare din Studio', function () {
    expect(app(CreateBlogPost::class)->canCreateAnother())->toBeFalse()
        ->and(app(CreateActualitate::class)->canCreateAnother())->toBeFalse()
        ->and(app(CreateGalleryAlbum::class)->canCreateAnother())->toBeFalse()
        ->and(app(CreateLibraryCategory::class)->canCreateAnother())->toBeFalse();
});

it('creează un articol de blog cu traduceri complete și conținut sanitizat', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');
    Storage::fake('public');

    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Titlu de test suficient de lung',
            'slug' => 'titlu-de-test',
            'excerpt' => 'Un rezumat scurt.',
            'content' => '<p>Conținut valid</p><script>alert(1)</script>',
            'image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
            'translations' => [
                'ru' => ['title' => 'Русский заголовок', 'slug' => 'russkij-zagolovok', 'excerpt' => 'Русское резюме.', 'content' => '<p>Текст</p>'],
                'en' => ['title' => 'English title', 'slug' => 'english-title', 'excerpt' => 'English summary.', 'content' => '<p>Text</p>'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->firstOrFail();

    expect($post->category)->toBe('blog')
        ->and($post->content)->not->toContain('<script>')
        ->and($post->translations()->where('locale', 'ru')->value('title'))->toBe('Русский заголовок')
        ->and($post->translations()->where('locale', 'en')->value('title'))->toBe('English title');
});

it('blochează salvarea unui articol dacă traducerile RU sau EN sunt incomplete', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Titlu în română, complet',
            'slug' => 'titlu-numai-ro',
            'excerpt' => 'Rezumat RO.',
            'content' => '<p>Conținut RO complet.</p>',
            'published_at' => now(),
            // RU/EN complet gol → validarea trebuie să blocheze salvarea.
        ])
        ->call('create')
        ->assertHasFormErrors([
            'translations.ru.title',
            'translations.ru.slug',
            'translations.ru.excerpt',
            'translations.ru.content',
            'translations.en.title',
            'translations.en.slug',
            'translations.en.excerpt',
            'translations.en.content',
        ]);

    expect(Post::query()->count())->toBe(0);
});

it('separă articolele pe categorie (scoping resurse)', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));

    Post::factory()->create(['category' => 'blog']);
    Post::factory()->create(['category' => 'actualitati']);

    expect(BlogResource::getEloquentQuery()->count())->toBe(1)
        ->and(ActualitatiResource::getEloquentQuery()->count())->toBe(1);
});

it('sanitizează HTML periculos păstrând tagurile permise', function () {
    $clean = app(SanitizeHtml::class)->handle('<p>Salut</p><script>alert(1)</script><h2>Titlu</h2>');

    expect($clean)->toContain('<p>Salut</p>')
        ->and($clean)->toContain('<h2>Titlu</h2>')
        ->and($clean)->not->toContain('<script>');
});

it('imageUrl trece URL-urile absolute și rezolvă căile stocate', function () {
    expect((new Post(['image' => 'https://columna.org.md/x.jpg']))->imageUrl())->toBe('https://columna.org.md/x.jpg')
        ->and((new Post(['image' => 'posts/a.webp']))->imageUrl())->toContain('/storage/posts/a.webp')
        ->and((new Post(['image' => null]))->imageUrl())->toBeNull();
});

it('arată „Vezi pe site" doar pentru articolele publicate', function () {
    Filament::setCurrentPanel(Filament::getPanel('content'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    $published = Post::factory()->create(['category' => 'blog', 'slug' => 'articol-publicat', 'published_at' => now()]);
    $draft = Post::factory()->create(['category' => 'blog', 'slug' => 'articol-ciorna', 'published_at' => null]);

    $this->get("/studio/blog/{$published->slug}/edit")
        ->assertOk()
        ->assertSee('/articol/articol-publicat', escape: false);

    $this->get("/studio/blog/{$draft->slug}/edit")
        ->assertOk()
        ->assertDontSee('/articol/articol-ciorna', escape: false);
});
