<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

/**
 * Secțiunea 2FA din profilul staff (Filament) — administrare peste ACELAȘI sistem Fortify
 * ca restul guard-ului web (nu MFA de panel).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    $this->actingAs($this->director);
});

it('profilul staff se randează cu secțiunea 2FA (stare inactivă)', function () {
    Livewire::test(EditProfile::class)
        ->assertOk()
        ->assertSee(__('panel.pages.profile.twofa.title'))
        ->assertSee(__('panel.pages.profile.twofa.status_off'));
});

it('activează 2FA din profil cu un cod TOTP valid + parola actuală', function () {
    // Pre-generăm secretul (mountUsing îl păstrează — force: false), ca să putem calcula OTP-ul.
    app(EnableTwoFactorAuthentication::class)($this->director, force: false);
    $secret = decrypt($this->director->refresh()->two_factor_secret);
    $otp = app(Google2FA::class)->getCurrentOtp($secret);

    Livewire::test(EditProfile::class)
        ->callAction(
            TestAction::make('enableTwoFactor')->schemaComponent('twoFactorActions'),
            data: ['code' => $otp, 'current_password' => 'password'],
        )
        ->assertHasNoErrors();

    expect($this->director->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('respinge activarea cu un cod TOTP greșit', function () {
    app(EnableTwoFactorAuthentication::class)($this->director, force: false);

    Livewire::test(EditProfile::class)
        ->callAction(
            TestAction::make('enableTwoFactor')->schemaComponent('twoFactorActions'),
            data: ['code' => '000000', 'current_password' => 'password'],
        );

    expect($this->director->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('dezactivează 2FA cu parola actuală', function () {
    app(EnableTwoFactorAuthentication::class)($this->director);
    $this->director->refresh()->forceFill(['two_factor_confirmed_at' => now()])->save();

    Livewire::test(EditProfile::class)
        ->callAction(
            TestAction::make('disableTwoFactor')->schemaComponent('twoFactorActions'),
            data: ['current_password' => 'password'],
        )
        ->assertHasNoErrors();

    $this->director->refresh();
    expect($this->director->two_factor_secret)->toBeNull()
        ->and($this->director->two_factor_confirmed_at)->toBeNull();
});

it('regenerează codurile de recuperare', function () {
    app(EnableTwoFactorAuthentication::class)($this->director);
    $this->director->refresh()->forceFill(['two_factor_confirmed_at' => now()])->save();
    $before = $this->director->refresh()->recoveryCodes();

    Livewire::test(EditProfile::class)
        ->callAction(
            TestAction::make('regenerateTwoFactorRecoveryCodes')->schemaComponent('twoFactorActions'),
            data: ['current_password' => 'password'],
        )
        ->assertHasNoErrors();

    expect($this->director->refresh()->recoveryCodes())->not->toBe($before);
});
