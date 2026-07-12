<?php

/**
 * Garda de normalizare a datelor din importul legacy (#37, descoperit LIVE): sursa veche conține
 * date corupte (an 2205 la note/absențe, 0026 la teme — typo-uri de operator) pe care importul le
 * copia verbatim, iar catalogul le afișa („16.09.2205" domina capul listei). clampDate le readuce
 * în fereastra plauzibilă, păstrând ziua/luna.
 */

use App\Console\Commands\ImportLegacy;

function clampDate(mixed $raw, string $start, string $end): string
{
    $method = new ReflectionMethod(ImportLegacy::class, 'clampDate');

    return $method->invoke(app(ImportLegacy::class), $raw, $start, $end);
}

it('corectează anul corupt păstrând ziua/luna (2205-09-16 → 2025-09-16, în semestrul I)', function () {
    expect(clampDate('2205-09-16', '2025-09-01', '2025-12-31'))->toBe('2025-09-16');
});

it('corectează teme cu an 0026 în fereastra anului școlar (0026-02-25 → 2026-02-25)', function () {
    expect(clampDate('0026-02-25', '2025-09-01', '2026-06-30'))->toBe('2026-02-25');
});

it('păstrează neatinsă o dată deja plauzibilă', function () {
    expect(clampDate('2025-10-05', '2025-09-01', '2025-12-31'))->toBe('2025-10-05');
});

it('data goală sau neparsabilă → începutul ferestrei', function () {
    expect(clampDate('', '2025-09-01', '2025-12-31'))->toBe('2025-09-01')
        ->and(clampDate(null, '2025-09-01', '2025-12-31'))->toBe('2025-09-01');
});

it('data la care corecția de an nu ajută → clamp la marginea corespunzătoare poziției originalului', function () {
    // Luna 07 (vară) nu intră în Sem I; corecția de an (2025-07-10) tot cade în afară → clamp. Data
    // originală (2205) e DUPĂ fereastră → capătul de sus (2025-12-31), nu începutul.
    expect(clampDate('2205-07-10', '2025-09-01', '2025-12-31'))->toBe('2025-12-31')
        // O dată corupt-veche (1990) e ÎNAINTE de fereastră → începutul.
        ->and(clampDate('1990-05-05', '2025-09-01', '2025-12-31'))->toBe('2025-09-01');
});
