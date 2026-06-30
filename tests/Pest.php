<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Resetează locale la default ÎNAINTE de fiecare test: unele teste setează app()->setLocale('ru'/'en')
    // și nu resetează (ex. ContentTranslationTest), iar locale-ul s-ar scurge în testele următoare
    // (ContentTranslator/enum-uri sensibile la limbă) → fragilitate de ordine. Reset = suită deterministă.
    ->beforeEach(function () {
        app()->setLocale(config('app.locale'));
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Header-uri pentru un Inertia partial reload, replicând exact logica de versiune din
 * `Inertia\Middleware::version()` (hash xxh128 al manifestului Vite). Necesar fiindcă middleware-ul
 * suprascrie pe fiecare request `Inertia::version(...)` cu această valoare — orice setare din
 * `beforeEach` e ignorată. Cu hash-ul ăsta, GET-ul partial trece de check-ul de versiune (fără 409).
 *
 * @return array<string, string>
 */
function inertiaPartialHeaders(string $component, string $only): array
{
    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $only,
    ];
}
