<?php

use App\Enums\UserRole;
use App\Filament\Widgets\SchoolTrendChart;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function trendChartUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('e vizibil conducerii + super-admin, dar NU administratorului tehnic', function () {
    $this->actingAs(trendChartUser(UserRole::Director));
    expect(SchoolTrendChart::canView())->toBeTrue();

    $this->actingAs(trendChartUser(UserRole::AdministratorOperational));
    expect(SchoolTrendChart::canView())->toBeTrue();

    // Super-adminul (break-glass, omniscient) îl vede — altfel contul IT nu ar vedea deloc graficul.
    $this->actingAs(trendChartUser(UserRole::Admin));
    expect(SchoolTrendChart::canView())->toBeTrue();

    // Administratorul TEHNIC = fără date academice (nici agregate).
    $this->actingAs(trendChartUser(UserRole::AdministratorTehnic));
    expect(SchoolTrendChart::canView())->toBeFalse();

    $this->actingAs(trendChartUser(UserRole::Profesor));
    expect(SchoolTrendChart::canView())->toBeFalse();
});

it('afișează implicit perioada de 6 luni în titlu', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->assertOk()
        ->assertSee('Activitate catalog')
        ->assertSee('6 luni');
});

it('selectorul schimbă perioada (1 / 3 luni) și titlul reflectă alegerea', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->set('filter', '1')
        ->assertOk()
        ->assertSee('1 lună')
        ->set('filter', '3')
        ->assertOk()
        ->assertSee('3 luni');
});

it('ignoră o valoare arbitrară din filtru și revine la 6 luni (whitelist)', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->set('filter', '999')
        ->assertOk()
        ->assertSee('6 luni');
});
