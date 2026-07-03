<?php

use App\Models\User;

/**
 * Contul de utilizator e auditat (L133 §7) pentru evenimentele de securitate (2FA, email,
 * parolă), dar secretele NU ajung niciodată în valorile auditului (auditExclude).
 */
it('auditează modificările contului', function () {
    config(['audit.console' => true]);

    $user = User::factory()->create();
    $user->update(['email' => 'audit-test@columna.md']);

    $audit = $user->audits()->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->getModified())->toHaveKey('email');
});

it('nu stochează secretele (parolă, secret 2FA, coduri de recuperare) în audit', function () {
    config(['audit.console' => true]);

    $user = User::factory()->create();
    $user->forceFill([
        'password' => bcrypt('parola-noua-123'),
        'two_factor_secret' => encrypt('secret-totp'),
        'two_factor_recovery_codes' => encrypt(json_encode(['cod-1', 'cod-2'])),
    ])->save();

    $audit = $user->audits()->latest('id')->first();

    // Scrierea a avut loc, dar niciun secret nu apare printre valorile auditate.
    $modified = $audit?->getModified() ?? [];

    expect($modified)->not->toHaveKey('password')
        ->and($modified)->not->toHaveKey('two_factor_secret')
        ->and($modified)->not->toHaveKey('two_factor_recovery_codes');
});
