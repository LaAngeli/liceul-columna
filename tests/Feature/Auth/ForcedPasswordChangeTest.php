<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('userul cu must_change_password e trimis la schimbarea parolei (cabinet)', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change'));
});

it('userul cu must_change_password e blocat și pe panoul Filament', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(UserRole::Profesor->value);

    $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
});

it('schimbarea parolei deblochează accesul', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)
        ->put('/schimbare-parola', [
            'password' => 'ParolaNoua123',
            'password_confirmation' => 'ParolaNoua123',
        ])
        ->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeFalse()
        ->and(Hash::check('ParolaNoua123', $fresh->password))->toBeTrue();
});

it('userul fără flag nu e redirecționat', function () {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('pagina de schimbare parolă e accesibilă chiar cu flag-ul activ', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get(route('password.change'))->assertOk();
});
