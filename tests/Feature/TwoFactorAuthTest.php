<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

/**
 * 2FA (TOTP Fortify) cap-coadă pe guard-ul `web` — fluxul cabinetului (endpoint-urile Fortify)
 * și challenge-ul unic de la login, comune pentru staff și elev/părinte.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function cabinetUser(): User
{
    $user = User::factory()->create(['email' => 'elev2fa@columna.test']);
    $user->assignRole(UserRole::Elev->value);

    return $user;
}

/** Activează + confirmă TOTP direct prin acțiunile Fortify (starea „2FA pornit"). */
function enableConfirmedTwoFactor(User $user): string
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();

    $secret = decrypt($user->two_factor_secret);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    return $secret;
}

it('activarea 2FA cere confirmarea parolei (password.confirm)', function () {
    $user = cabinetUser();

    $response = $this->actingAs($user)->post('/user/two-factor-authentication');

    // Fără parola confirmată în sesiune → Fortify redirecționează la pagina de confirmare.
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('confirm-password');
});

it('după confirmarea parolei, activarea generează secretul + codurile de recuperare', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'password'])
        ->assertRedirect();

    $this->post('/user/two-factor-authentication')->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('confirmarea cu un cod TOTP valid finalizează activarea', function () {
    $user = cabinetUser();

    $this->actingAs($user)->post('/user/confirm-password', ['password' => 'password']);
    $this->post('/user/two-factor-authentication');

    $secret = decrypt($user->refresh()->two_factor_secret);
    $otp = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/user/confirmed-two-factor-authentication', ['code' => $otp])
        ->assertRedirect();

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('cu 2FA confirmat, login-ul cere challenge-ul, apoi codul TOTP finalizează autentificarea', function () {
    $user = cabinetUser();
    $secret = enableConfirmedTwoFactor($user);

    // Pasul 1: credențiale corecte → NU ești logat, ești trimis la challenge.
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertRedirect('/two-factor-challenge');
    $this->assertGuest('web');

    // Pasul 2: codul TOTP valid pe challenge → autentificat + trimis la pagina rolului.
    $otp = app(Google2FA::class)->getCurrentOtp($secret);
    $challenge = $this->post('/two-factor-challenge', ['code' => $otp]);

    $challenge->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
});

it('un cod de recuperare valid trece challenge-ul', function () {
    $user = cabinetUser();
    enableConfirmedTwoFactor($user);
    $recoveryCode = $user->refresh()->recoveryCodes()[0];

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode]);

    $this->assertAuthenticatedAs($user, 'web');
});

it('un cod TOTP greșit NU trece challenge-ul', function () {
    $user = cabinetUser();
    enableConfirmedTwoFactor($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => '000000']);

    $this->assertGuest('web');
});

it('fără 2FA, login-ul intră direct (fără challenge)', function () {
    $user = cabinetUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user, 'web');
});

it('profilul cabinet expune starea 2FA (prop twoFactor)', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->get('/cabinet/profil')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cabinet/profile')
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.requiresConfirmation', true));
});
