<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Numele contului (identitatea) e read-only în profilul personal pentru TOȚI, în afară de
 * super-admin. Conturile sunt create de administrație → corecțiile de nume se fac din resursa
 * „Utilizatori", nu din „profilul meu". Blocarea e server-side (disabled + fără dehidratare).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function nameLockUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

it('super-adminul își poate edita numele în profil (câmp activ)', function () {
    nameLockUserWithRole(UserRole::Admin->value);

    Livewire::test(EditProfile::class)->assertFormFieldIsEnabled('name');
});

it('numele e read-only în profil pentru non-super-admin', function (string $role) {
    nameLockUserWithRole($role);

    Livewire::test(EditProfile::class)->assertFormFieldIsDisabled('name');
})->with([
    UserRole::Director->value,
    UserRole::AdministratorOperational->value,
    UserRole::Profesor->value,
    UserRole::Diriginte->value,
]);

it('non-super-adminul NU poate schimba numele nici prin save (server-side)', function () {
    $user = nameLockUserWithRole(UserRole::Profesor->value);
    $original = $user->name;

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nume Falsificat'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe($original);
});

it('super-adminul chiar schimbă numele la save', function () {
    $user = nameLockUserWithRole(UserRole::Admin->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nume Nou Admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Nume Nou Admin');
});
