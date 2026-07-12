<?php

/**
 * Vectori de securitate din maparea cabinetului (#37): resetarea parolei stinge flag-ul de schimbare
 * forțată, iar emailul de login (identificator + destinație OTP 2FA) nu se poate SCHIMBA din cabinet
 * odată setat — doar prima setare (userii migrați cu email gol).
 */

use App\Actions\Fortify\ResetUserPassword;
use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('resetarea parolei prin „am uitat parola" stinge must_change_password', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'ParolaResetata123',
        'password_confirmation' => 'ParolaResetata123',
    ]);

    expect($user->fresh()->must_change_password)->toBeFalse();
});

it('familia își poate SETA prima dată emailul (cont migrat cu email gol); email_verified_at se resetează', function () {
    $user = User::factory()->create([
        'email' => null,
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $user->assignRole(UserRole::Parinte->value);

    actingAs($user)
        ->put(route('cabinet.notifications.settings.update'), [
            'email' => 'parinte.nou@example.com',
            'preferences' => [],
            'contacts' => [],
        ])
        ->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('parinte.nou@example.com')
        ->and($fresh->email_verified_at)->toBeNull();
});

it('familia NU poate SCHIMBA un email deja setat din cabinet (vector de preluare cont)', function () {
    $user = User::factory()->create([
        'email' => 'parinte@example.com',
        'must_change_password' => false,
    ]);
    $user->assignRole(UserRole::Parinte->value);

    actingAs($user)
        ->put(route('cabinet.notifications.settings.update'), [
            'email' => 'atacator@example.com',
            'preferences' => [],
            'contacts' => [],
        ])
        ->assertSessionHasErrors(['email']);

    // Adresa NU s-a schimbat.
    expect($user->fresh()->email)->toBe('parinte@example.com');
});

it('salvarea preferințelor cu emailul neschimbat trece fără eroare', function () {
    $user = User::factory()->create(['email' => 'parinte@example.com', 'must_change_password' => false]);
    $user->assignRole(UserRole::Parinte->value);

    actingAs($user)
        ->put(route('cabinet.notifications.settings.update'), [
            'email' => 'parinte@example.com', // aceeași adresă → no-op, nu eroare
            'preferences' => [],
            'contacts' => ['telegram' => '@parinte'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->fresh()->notification_contacts)->toBe(['telegram' => '@parinte']);
});
