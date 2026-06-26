<?php

use App\Models\Post;
use App\Models\PostTranslation;

it('curăță shortcode-urile builder și normalizează paragrafele', function () {
    $post = Post::factory()->create([
        'content' => '[vc_row type="in_container"][vc_column][vc_column_text]<p>Intro.</p>'."\n".'Paragraf de text simplu.[/vc_column_text][/vc_column][/vc_row]',
        'excerpt' => '[vc_row type="in_container" full_width="stretch', // shortcode trunchiat (fără `]`)
    ]);

    $this->artisan('app:strip-post-shortcodes')->assertSuccessful();

    $post->refresh();
    expect($post->content)->not->toContain('[vc')
        ->and($post->content)->toContain('<p>Intro.</p>')
        ->and($post->content)->toContain('<p>Paragraf de text simplu.</p>')
        ->and($post->excerpt)->not->toContain('[')
        ->and($post->excerpt)->toContain('Intro'); // regenerat din conținutul curat
});

it('scoate comentariile de bloc Gutenberg și paragrafele goale', function () {
    $post = Post::factory()->create([
        'content' => '<!-- wp:image {"id":210} --><p><!-- wp:paragraph --></p>'."\n".'<h3>Titlu</h3>[vc_separator]',
        'excerpt' => 'curat',
    ]);

    $this->artisan('app:strip-post-shortcodes')->assertSuccessful();

    expect($post->refresh()->content)->toBe('<h3>Titlu</h3>');
});

it('golește excerpt-urile traduse care sunt garbage de builder', function () {
    $post = Post::factory()->create(['content' => 'curat', 'excerpt' => 'curat']);
    $translation = PostTranslation::factory()->for($post)->create([
        'locale' => 'ru',
        'excerpt' => '[vc_row type="in_container', // garbage trunchiat
    ]);

    $this->artisan('app:strip-post-shortcodes')->assertSuccessful();

    expect($translation->refresh()->excerpt)->toBeNull();
});
