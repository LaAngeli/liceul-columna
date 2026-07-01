<?php

use Inertia\Testing\AssertableInertia as Assert;

it('randează pagina de eroare brand-uită pentru un 404, păstrând statusul', function () {
    $this->get('/aceasta-pagina-nu-exista')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/error')
            ->where('status', 404)
            ->where('locale', 'ro'));
});

it('randează pagina de eroare în limba prefixului URL', function (string $uri, string $locale) {
    $this->get($uri)
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/error')
            ->where('status', 404)
            ->where('locale', $locale));
})->with([
    'rusă' => ['/ru/pagina-inexistenta', 'ru'],
    'engleză' => ['/en/pagina-inexistenta', 'en'],
    'română (root)' => ['/alt-url-gresit', 'ro'],
]);

it('partajează traducerile (messages) către pagina de eroare', function () {
    $this->get('/url-inexistent-cu-traduceri')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/error')
            ->has('messages.ro.error_page.status.404.title')
            ->has('messages.ru.error_page.status.404.title')
            ->has('messages.en.error_page.status.404.title'));
});

it('păstrează 404 JSON pentru rutele API (nu pagina Inertia)', function () {
    $response = $this->getJson('/api/ruta-inexistenta');

    $response->assertNotFound();

    expect($response->headers->get('content-type'))->toContain('application/json');
});
