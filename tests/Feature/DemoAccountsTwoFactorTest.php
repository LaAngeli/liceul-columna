<?php

use App\Console\Commands\DemoAccounts;
use App\Models\User;

/**
 * `app:demo-accounts --reset-2fa` deblochează testarea, dar are o singură regulă de care depinde
 * totul: atinge EXCLUSIV conturile marcate `[DEMO]`. Un cont real căruia i s-ar șterge 2FA e o
 * slăbire tăcută de securitate pe date de minori.
 */

/** Un cont cu 2FA activ — TOTP, email sau ambele. */
function accountWithTwoFactor(string $name, bool $totp = true, bool $email = true): User
{
    return User::factory()->create([
        'name' => $name,
        'two_factor_secret' => $totp ? encrypt('SECRET') : null,
        'two_factor_recovery_codes' => $totp ? encrypt(json_encode(['aaa-bbb'])) : null,
        'two_factor_confirmed_at' => $totp ? now() : null,
        'two_factor_email_enabled_at' => $email ? now() : null,
    ]);
}

it('dezactivează 2FA pe conturile demo și NU atinge conturile reale', function () {
    $demo = accountWithTwoFactor(DemoAccounts::MARKER.' Profesor');
    $real = accountWithTwoFactor('Bujor-Cobili Carolina');

    $this->artisan('app:demo-accounts --reset-2fa')->assertSuccessful();

    expect($demo->fresh()->hasTwoFactorConfigured())->toBeFalse()
        // Contul REAL își păstrează 2FA — altfel comanda ar fi o portiță de securitate.
        ->and($real->fresh()->hasTwoFactorConfigured())->toBeTrue();
});

it('curăță toate cele patru câmpuri, nu doar secretul TOTP', function () {
    $demo = accountWithTwoFactor(DemoAccounts::MARKER.' Elev');

    $this->artisan('app:demo-accounts --reset-2fa')->assertSuccessful();

    $fresh = $demo->fresh();

    expect($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull()
        // Cel mai ușor de uitat: 2FA pe EMAIL provoacă login-ul de una singură, fără TOTP.
        ->and($fresh->two_factor_email_enabled_at)->toBeNull();
});

it('deblochează și contul cu 2FA DOAR pe email', function () {
    $demo = accountWithTwoFactor(DemoAccounts::MARKER.' Părinte', totp: false, email: true);

    expect($demo->hasTwoFactorConfigured())->toBeTrue();

    $this->artisan('app:demo-accounts --reset-2fa')->assertSuccessful();

    expect($demo->fresh()->hasTwoFactorConfigured())->toBeFalse();
});

it('spune clar când nu are ce debloca', function () {
    User::factory()->create(['name' => DemoAccounts::MARKER.' Fără 2FA']);

    $this->artisan('app:demo-accounts --reset-2fa')
        ->expectsOutputToContain('Niciun cont demo nu are 2FA activ')
        ->assertSuccessful();
});
