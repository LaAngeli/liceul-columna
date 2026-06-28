<?php

use App\Enums\UserRole;
use App\Filament\Pages\Calendar;
use App\Models\Holiday;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Modul Calendar C6: pagina Filament „Calendar" (staff) consumă același agregator, cu scope
 * instituțional (structură + sesiuni publicate), fără PII per-elev. Navigare pe lună.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('pagina de calendar staff afișează evenimentele instituționale', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Director->value);

    Holiday::create([
        'name' => 'Vacanță instituțională',
        'starts_on' => now()->startOfMonth()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($staff);

    Livewire::test(Calendar::class)
        ->assertOk()
        ->assertSee('Vacanță instituțională');
});

it('navigarea schimbă luna afișată', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Director->value);

    $this->actingAs($staff);

    Livewire::test(Calendar::class)
        ->assertSet('month', now()->format('Y-m'))
        ->call('previousMonth')
        ->assertSet('month', now()->subMonthNoOverflow()->format('Y-m'))
        ->call('goToday')
        ->assertSet('month', now()->format('Y-m'));
});
