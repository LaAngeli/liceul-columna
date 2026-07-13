<?php

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * `app:localize-post-images` — descarcă local asset-urile columna.org.md ale articolelor (imagini +
 * PDF), re-pointează `image`/`content` la căi locale și schimbă linkurile de pagină rămase la columna.md.
 */
beforeEach(function () {
    Storage::fake('public');
});

it('descarcă asset-urile local și re-pointează image (relativ) + content (/storage) + link (columna.md)', function () {
    Http::fake(['*' => Http::response('FAKE-BYTES', 200)]);

    $post = Post::factory()->create([
        'image' => 'https://columna.org.md/wp-content/uploads/2022/01/hero.png',
        'content' => '<p><img src="https://www.columna.org.md/wp-content/uploads/2021/05/foto.jpg"></p>'
            .'<a href="https://columna.org.md/orarul-examenelor/">aici</a>',
    ]);

    $this->artisan('app:localize-post-images')->assertSuccessful();

    Storage::disk('public')->assertExists('posts/imported/2022/01/hero.png');
    Storage::disk('public')->assertExists('posts/imported/2021/05/foto.jpg');

    $post->refresh();
    expect($post->image)->toBe('posts/imported/2022/01/hero.png');
    expect($post->content)
        ->toContain('/storage/posts/imported/2021/05/foto.jpg')
        ->toContain('https://columna.md/orarul-examenelor/')
        ->not->toContain('columna.org.md');
});

it('localizează și conținutul traducerilor RU/EN', function () {
    Http::fake(['*' => Http::response('IMG', 200)]);

    $post = Post::factory()->create(['image' => null, 'content' => '<p>ro</p>']);
    PostTranslation::factory()->create([
        'post_id' => $post->id,
        'locale' => 'ru',
        'content' => '<img src="https://columna.org.md/wp-content/uploads/2020/10/ru.jpg">',
    ]);

    $this->artisan('app:localize-post-images')->assertSuccessful();

    Storage::disk('public')->assertExists('posts/imported/2020/10/ru.jpg');
    expect(PostTranslation::query()->where('post_id', $post->id)->value('content'))
        ->toContain('/storage/posts/imported/2020/10/ru.jpg')
        ->not->toContain('columna.org.md');
});

it('lasă asset-ul neatins dacă descărcarea eșuează (404)', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $post = Post::factory()->create([
        'image' => 'https://columna.org.md/wp-content/uploads/2022/01/lipsa.png',
        'content' => '<p>fără asset</p>',
    ]);

    $this->artisan('app:localize-post-images')->assertSuccessful();

    Storage::disk('public')->assertMissing('posts/imported/2022/01/lipsa.png');
    expect($post->refresh()->image)->toBe('https://columna.org.md/wp-content/uploads/2022/01/lipsa.png');
});

it('este idempotentă — a doua rulare nu mai are ce localiza', function () {
    Http::fake(['*' => Http::response('IMG', 200)]);
    $post = Post::factory()->create([
        'image' => 'https://columna.org.md/wp-content/uploads/2022/01/x.png',
        'content' => '<p>x</p>',
    ]);

    $this->artisan('app:localize-post-images')->assertSuccessful();
    expect($post->refresh()->image)->toBe('posts/imported/2022/01/x.png');

    // A doua rulare: nimic columna.org.md rămas → fără modificări.
    $this->artisan('app:localize-post-images')->assertSuccessful();
    expect($post->refresh()->image)->toBe('posts/imported/2022/01/x.png');
});
