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
        ->call('previous')
        ->assertSet('month', now()->subMonthNoOverflow()->format('Y-m'))
        ->call('goToday')
        ->assertSet('month', now()->format('Y-m'));
});

it('toggleCategory scoate apoi repune o categorie din vizibile, dar refuză să golească lista', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Director->value);
    $this->actingAs($staff);

    $component = Livewire::test(Calendar::class);
    $initial = $component->get('visibleCategories');
    expect($initial)->toContain('homework');

    $component->call('toggleCategory', 'homework');
    expect($component->get('visibleCategories'))->not->toContain('homework');

    $component->call('toggleCategory', 'homework');
    expect($component->get('visibleCategories'))->toContain('homework');

    // Goli toate prin toggle succesive — ultima activă rămâne (UI safety).
    $current = $component->get('visibleCategories');
    foreach ($current as $key) {
        $component->call('toggleCategory', $key);
    }
    expect($component->get('visibleCategories'))->toHaveCount(1);
});

it('selectEvent + closeEvent setează și resetează id-ul evenimentului din modal', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Director->value);
    $this->actingAs($staff);

    Livewire::test(Calendar::class)
        ->assertSet('selectedEventId', null)
        ->call('selectEvent', 'homework:42')
        ->assertSet('selectedEventId', 'homework:42')
        ->call('closeEvent')
        ->assertSet('selectedEventId', null);
});

it('butonul de adăugare e vizibil conducerii și ascuns profesorului fără clase', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);
    expect(app(Calendar::class)->canAddEvent())->toBeTrue();

    $prof = User::factory()->create();
    $prof->assignRole(UserRole::Profesor->value);
    $this->actingAs($prof);
    expect(app(Calendar::class)->canAddEvent())->toBeFalse();
});
