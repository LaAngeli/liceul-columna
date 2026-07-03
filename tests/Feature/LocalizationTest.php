<?php

use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

it('locale-ul aplicației este RO', function () {
    expect(app()->getLocale())->toBe('ro');
});

it('mesajele de validare sunt traduse în RO, nu chei brute', function () {
    expect(trans('validation.required'))->not->toBe('validation.required')
        ->and(trans('validation.unique'))->not->toBe('validation.unique')
        ->and(trans('validation.email'))->not->toBe('validation.email')
        ->and(trans('validation.min.string'))->not->toBe('validation.min.string')
        ->and(trans('auth.failed'))->not->toBe('auth.failed')
        ->and(trans('passwords.sent'))->not->toBe('passwords.sent');
});

it('string-urile JSON sunt traduse', function () {
    expect(__('Password updated.'))->toBe('Parola a fost actualizată.')
        ->and(__('Profile updated.'))->toBe('Profilul a fost actualizat.');
});

it('mesajul pentru email duplicat e în RO (scenariul din panou)', function () {
    User::factory()->create(['email' => 'existent@columna.test']);

    $validator = Validator::make(
        ['email' => 'existent@columna.test'],
        ['email' => 'required|email|unique:users,email'],
    );

    $message = $validator->errors()->first('email');

    expect($message)->not->toBe('validation.unique')
        ->and($message)->toContain('email');
});

it('servește RO la root, fără prefix', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/home')->where('locale', 'ro'));
});

it('servește paginile cu prefix /ru și /en', function (string $url, string $locale) {
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
})->with([
    'ru — acasă' => ['/ru', 'ru'],
    'en — acasă' => ['/en', 'en'],
    'ru — pagină' => ['/ru/pochemu-columna', 'ru'],
    'en — pagină' => ['/en/contact', 'en'],
]);

it('partajează limbile și traducerile către frontend', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('locales.ro')->has('locales.ru')->has('locales.en')
            ->has('messages.ro')->has('messages.ru')->has('messages.en'));
});

it('comută limba și salvează preferința pe user + cookie', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->get('/set-locale/ru?redirect=/dashboard')
        ->assertRedirect('/dashboard')
        ->assertCookie('locale', 'ru');

    expect($user->fresh()->locale)->toBe('ru');
});

it('respinge o limbă necunoscută', function () {
    $this->get('/set-locale/de')->assertNotFound();
});

it('SetUserLocale aplică limba din cookie pentru oaspeți (rutele Fortify, ex. /login)', function (string $locale) {
    $request = Request::create('/login', 'GET');
    $request->cookies->set('locale', $locale);

    app(SetUserLocale::class)->handle($request, fn () => response(''));

    expect(app()->getLocale())->toBe($locale);
})->with(['ru', 'en']);

it('rutele Fortify trec prin SetUserLocale', function () {
    $middleware = collect(app('router')->getRoutes()->getByName('login')->gatherMiddleware());

    expect($middleware)->toContain(SetUserLocale::class);
});
