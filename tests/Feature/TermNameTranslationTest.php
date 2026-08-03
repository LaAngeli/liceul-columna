<?php

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\ContentTranslator;

/**
 * Numele semestrului e o valoare de REGISTRU: se scrie o singură dată în română (limba oficială a
 * documentelor) și se traduce abia la afișare. Cele două jumătăți trebuie să rămână lipite — o
 * traducere la afișare nu valorează nimic dacă valoarea stocată variază cu limba interfeței.
 */
it('numele canonic se scrie în română indiferent de limba interfeței', function (string $locale) {
    app()->setLocale($locale);

    // Fără forțarea RO, un administrator care lucrează în rusă ar fi salvat „Семестр I" — nume care
    // n-ar mai fi potrivit niciun dicționar și ar fi apărut ca atare și utilizatorilor români.
    expect(Term::canonicalName(1))->toBe('Semestrul I')
        ->and(Term::canonicalName(2))->toBe('Semestrul II');
})->with(['ro', 'ru', 'en']);

it('semestrul creat sub o interfață rusă păstrează numele de registru', function () {
    app()->setLocale('ru');

    $year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
    $term = Term::factory()->for($year)->create([
        'number' => 1, 'name' => '', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31',
    ]);

    expect($term->refresh()->name)->toBe('Semestrul I');
});

it('afișarea traduce numele în RU/EN, cu fallback pe RO', function () {
    expect(ContentTranslator::term('Semestrul I', 'ru'))->toBe('Семестр I')
        ->and(ContentTranslator::term('Semestrul II', 'ru'))->toBe('Семестр II')
        ->and(ContentTranslator::term('Semestrul I', 'en'))->toBe('Term I')
        ->and(ContentTranslator::term('Semestrul II', 'en'))->toBe('Term II')
        // RO rămâne neatins, iar o denumire custom (editată justificat) cade pe ea însăși.
        ->and(ContentTranslator::term('Semestrul I', 'ro'))->toBe('Semestrul I')
        ->and(ContentTranslator::term('Trimestrul de vară', 'ru'))->toBe('Trimestrul de vară');
});
