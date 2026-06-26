<?php

use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

it('listează articolele din categoria Actualități', function () {
    Post::factory()->actualitati()->create(['published_at' => now()->subDay()]);
    Post::factory()->blog()->create(['published_at' => now()->subDay()]);

    $this->get('/actualitati-si-evenimente')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/articole/index')
            ->where('category', 'actualitati')
            ->has('posts.data', 1));
});

it('listează articolele din categoria Blog', function () {
    Post::factory()->blog()->create(['published_at' => now()->subDay()]);

    $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/articole/index')->has('posts.data', 1));
});

it('afișează un articol individual', function () {
    $post = Post::factory()->create(['slug' => 'articol-de-test', 'published_at' => now()->subDay()]);

    $this->get('/articol/articol-de-test')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/articole/show')->where('post.title', $post->title));
});

it('nu afișează articolele nepublicate', function () {
    Post::factory()->create(['slug' => 'ciornă', 'published_at' => null]);

    $this->get('/articol/ciornă')->assertNotFound();
});

it('afișează ultimele actualități pe pagina principală', function () {
    Post::factory()->actualitati()->create(['published_at' => now()->subDay()]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/home')->has('latestNews', 1));
});
