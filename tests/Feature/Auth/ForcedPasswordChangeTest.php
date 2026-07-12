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

// SECURITATE (#37): endpoint-ul e EXCLUSIV fluxul forțat — un user deja onboardat (flag=false) nu-și
// poate seta o parolă nouă fără cea curentă (altfel o sesiune deschisă = preluare de cont).

it('userul FĂRĂ flag NU poate schimba parola prin endpoint-ul forțat (redirect, parola neschimbată)', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'password' => Hash::make('ParolaVeche123'),
    ]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)
        ->put('/schimbare-parola', [
            'password' => 'ParolaInjectata123',
            'password_confirmation' => 'ParolaInjectata123',
        ])
        ->assertRedirect();

    // Parola NU s-a schimbat — cea veche rămâne validă.
    expect(Hash::check('ParolaVeche123', $user->fresh()->password))->toBeTrue();
});

it('userul FĂRĂ flag e redirecționat de la pagina GET de schimbare forțată', function () {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get(route('password.change'))->assertRedirect();
});
