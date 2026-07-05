<?php

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $year = AcademicYear::factory()->create();
    // Sem I marcat GREȘIT curent la început — comanda trebuie să corecteze după dată.
    $this->semI = Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => true,
    ]);
    $this->semII = Term::factory()->for($year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => false,
    ]);
});

it('marchează curent semestrul care conține data de azi', function () {
    $this->travelTo(Carbon::parse('2026-03-10'));

    $this->artisan('app:sync-current-term')->assertSuccessful();

    expect($this->semII->refresh()->is_current)->toBeTrue()
        ->and($this->semI->refresh()->is_current)->toBeFalse();
});

it('în vacanță (după toate semestrele) alege cel mai recent semestru început', function () {
    $this->travelTo(Carbon::parse('2026-07-15'));

    $this->artisan('app:sync-current-term')->assertSuccessful();

    expect($this->semII->refresh()->is_current)->toBeTrue();
});

it('înainte de primul semestru alege primul care urmează', function () {
    $this->travelTo(Carbon::parse('2025-08-15'));

    $this->artisan('app:sync-current-term')->assertSuccessful();

    expect($this->semI->refresh()->is_current)->toBeTrue()
        ->and($this->semII->refresh()->is_current)->toBeFalse();
});

it('setează mereu exact un singur semestru curent', function () {
    $this->travelTo(Carbon::parse('2025-10-15'));

    $this->artisan('app:sync-current-term')->assertSuccessful();

    expect(Term::query()->where('is_current', true)->count())->toBe(1)
        ->and($this->semI->refresh()->is_current)->toBeTrue();
});
