<?php

use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use App\Support\RouteSlugs;
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

// === Sincronizare GLOBALĂ a limbii (site public ↔ cabinet/staff) ===
// Bug: site-ul public deducea limba DOAR din prefixul URL, ignorând preferința salvată. Astfel,
// intrând pe un URL fără prefix (logo/„vezi site-ul" din zona autentificată) se cădea pe RO, deși
// utilizatorul alesese RU/EN. Fix: `SetPublicLocale` redirectează (302) URL-urile NON-prefixate la
// varianta prefixată când preferința (user.locale/cookie) cere o limbă non-implicită.

it('site-ul public respectă user.locale: RU logat pe „/" e dus la „/ru"', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->actingAs($user)->get('/')->assertRedirect('/ru');
});

it('site-ul public respectă preferința și pe pagini interioare, păstrând query-ul', function () {
    $user = User::factory()->create(['locale' => 'ru']);
    $expected = '/ru'.RouteSlugs::translatePath('/scoala-primara', 'ru').'?x=1';

    $this->actingAs($user)->get('/scoala-primara?x=1')->assertRedirect($expected);
});

it('redirectul de sincronizare NU buclează — „/ru" se randează 200', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    // URL deja prefixat → autoritar, fără redirect (altfel ar fi buclă infinită).
    $this->actingAs($user)->get('/ru')->assertOk();
});

it('RO (implicit) NU declanșează redirect pe site', function () {
    $user = User::factory()->create(['locale' => 'ro']);

    $this->actingAs($user)->get('/')->assertOk();
});

it('un URL explicit prefixat rămâne autoritar chiar dacă preferința diferă (linkuri partajate)', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    // Preferință RU, dar linkul e explicit /en → EN câștigă (nu redirectăm prefixat→prefixat).
    $this->actingAs($user)->get('/en')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('SetPublicLocale redirectează un URL fără prefix după cookie (oaspete)', function () {
    $request = Request::create('/', 'GET');
    $request->cookies->set('locale', 'ru');

    $response = app(SetPublicLocale::class)->handle($request, fn () => response('NEXT'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toEndWith('/ru');
});

it('SetPublicLocale NU redirectează cererile non-GET (guard pe metodă)', function () {
    $request = Request::create('/contacte', 'POST');
    $request->cookies->set('locale', 'ru');

    $response = app(SetPublicLocale::class)->handle($request, fn () => response('NEXT'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('NEXT');
});

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

it('cabinetul nu are variantă cu prefix de limbă — switcher-ul trebuie să redirecteze FĂRĂ prefix', function () {
    // Regresie: switcher-ul din cabinet construia redirect `/ru/dashboard` (prin `localizePath`),
    // dar zona autentificată NU are rute cu prefix (limba vine din cookie/user) → 404. Fix: în
    // cabinet `LanguageSwitcher` primește `prefixed={false}` → redirect pe URL-ul curent, neatins.
    $user = User::factory()->create(['locale' => 'ru']);

    // URL-ul cu prefix (cel construit GREȘIT înainte) nu există pentru cabinet.
    $this->actingAs($user)->get('/ru/dashboard')->assertNotFound();

    // Calea CORECTĂ: cabinetul fără prefix se randează, limba venind din preferința salvată.
    $this->actingAs($user)->get('/dashboard')->assertOk();
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
