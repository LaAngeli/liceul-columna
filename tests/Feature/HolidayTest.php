<?php

use App\Models\Holiday;
use App\Support\Holidays;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;

/**
 * Modul Calendar C1: modelul Holiday e sursa unică a „zilei nelucrătoare", folosită de WorkingDays
 * (termenele motivărilor) și, ulterior, de calendar/orar. 2026-06-01 = luni; 06-06/06-07 = weekend.
 */
it('WorkingDays sare peste o sărbătoare', function () {
    // Fără sărbătoare: luni 06-01 + 2 zile lucrătoare = miercuri 06-03.
    // Cu marți 06-02 sărbătoare: miercuri devine prima zi → joi 06-04 e a doua.
    Holiday::create(['name' => 'Zi de test', 'starts_on' => '2026-06-02']);

    $result = WorkingDays::add(Carbon::parse('2026-06-01'), 2);

    expect($result->toDateString())->toBe('2026-06-04');
});

it('WorkingDays sare doar weekendurile când nu există sărbători', function () {
    // Vineri 06-05 + 1 zi lucrătoare = luni 06-08 (sare sâmbătă + duminică).
    $result = WorkingDays::add(Carbon::parse('2026-06-05'), 1);

    expect($result->toDateString())->toBe('2026-06-08');
});

it('un interval de vacanță marchează toate zilele ca nelucrătoare', function () {
    Holiday::create(['name' => 'Vacanță', 'starts_on' => '2026-06-02', 'ends_on' => '2026-06-04']);

    expect(Holidays::isNonWorkingDay(Carbon::parse('2026-06-03')))->toBeTrue()
        ->and(Holidays::isHoliday(Carbon::parse('2026-06-04')))->toBeTrue()
        ->and(Holidays::isHoliday(Carbon::parse('2026-06-05')))->toBeFalse();
});

it('invalidează cache-ul când se adaugă o sărbătoare', function () {
    // Prima interogare populează cache-ul gol.
    expect(Holidays::isHoliday(Carbon::parse('2026-06-10')))->toBeFalse();

    Holiday::create(['name' => 'Adăugată ulterior', 'starts_on' => '2026-06-10']);

    // Evenimentul saved() a golit cache-ul → noua sărbătoare e vizibilă.
    expect(Holidays::isHoliday(Carbon::parse('2026-06-10')))->toBeTrue();
});
