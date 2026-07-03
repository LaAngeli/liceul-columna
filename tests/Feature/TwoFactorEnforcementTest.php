<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Obligativitatea 2FA (gate-ul EnsureTwoFactorEnrolled, rollout fazat din config/security.php)
 * + resetarea 2FA de către staff (recuperare cont, cu motiv + audit).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function staffUser(string $role = 'profesor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function cabinetMember(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);

    return $user;
}

it('staff-ul fără 2FA e blocat pe pagina de configurare (required_staff=true)', function () {
    config(['security.two_factor.required_staff' => true]);

    $this->actingAs(staffUser())
        ->get('/admin')
        ->assertRedirect(route('two-factor.setup'));
});

it('staff-ul cu TOTP confirmat trece de gate', function () {
    config(['security.two_factor.required_staff' => true]);
    $staff = staffUser();
    app(EnableTwoFactorAuthentication::class)($staff);
    $staff->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($staff)->get('/admin')->assertOk();
});

it('cabinetul NU e gated cât timp required_cabinet=false', function () {
    config(['security.two_factor.required_staff' => true, 'security.two_factor.required_cabinet' => false]);

    $this->actingAs(cabinetMember())
        ->get(route('dashboard'))
        ->assertOk();
});

it('la comutarea required_cabinet=true, elevul/părintele fără 2FA e gated', function () {
    config(['security.two_factor.required_cabinet' => true]);

    $this->actingAs(cabinetMember())
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.setup'));
});

it('utilizatorul cu 2FA pe email trece de gate', function () {
    config(['security.two_factor.required_cabinet' => true]);
    $member = cabinetMember();
    $member->forceFill(['email' => 'gated@columna.test', 'two_factor_email_enabled_at' => now()])->save();

    $this->actingAs($member)->get(route('dashboard'))->assertOk();
});

it('pagina de configurare + endpoint-urile de activare rămân accesibile sub gate', function () {
    config(['security.two_factor.required_staff' => true]);
    $staff = staffUser();

    // Flag-ul de login e setat de listener; aici simulăm sesiunea post-login.
    $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('two-factor.setup'))->assertOk();
    $this->post(route('two-factor.enable'))->assertRedirect();

    expect($staff->refresh()->two_factor_secret)->not->toBeNull();
});

it('logout-ul rămâne accesibil sub gate', function () {
    config(['security.two_factor.required_staff' => true]);

    $this->actingAs(staffUser())->post('/logout')->assertRedirect();
    $this->assertGuest('web');
});

it('login-ul marchează parola drept confirmată (fluxul de activare nu o cere a doua oară)', function () {
    $user = cabinetMember();
    $user->forceFill(['email' => 'flag@columna.test'])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});

it('pagina forțată expune ambele metode + ținta de continuare', function () {
    config(['security.two_factor.required_cabinet' => true]);
    $member = cabinetMember();

    $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.setup'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/two-factor-setup')
            ->where('configured', false)
            ->where('continueTo', route('dashboard'))
            ->has('twoFactor.email'));
});

it('directorul resetează 2FA unui profesor: metode golite + motiv păstrat', function () {
    $director = staffUser(UserRole::Director->value);
    $target = staffUser();
    app(EnableTwoFactorAuthentication::class)($target);
    $target->forceFill([
        'two_factor_confirmed_at' => now(),
        'two_factor_email_enabled_at' => now(),
        'email' => 'tinta@columna.test',
    ])->save();

    $this->actingAs($director);

    Livewire::test(ListUsers::class)
        ->callAction(
            TestAction::make('resetTwoFactor')->table($target),
            data: ['reason' => 'Telefon pierdut — cerere verificată la secretariat.'],
        )
        ->assertHasNoErrors();

    $target->refresh();
    expect($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_confirmed_at)->toBeNull()
        ->and($target->two_factor_email_enabled_at)->toBeNull()
        ->and($target->hasTwoFactorConfigured())->toBeFalse()
        ->and($target->two_factor_reset_reason)->toBe('Telefon pierdut — cerere verificată la secretariat.')
        ->and($target->two_factor_reset_by_user_id)->toBe($director->id);
});

it('profesorul NU vede acțiunea de resetare (nu administrează conturi)', function () {
    $teacher = staffUser();
    $target = staffUser();
    app(EnableTwoFactorAuthentication::class)($target);
    $target->forceFill(['two_factor_confirmed_at' => now()])->save();

    // Profesorul nici nu poate deschide lista de utilizatori (gated pe canManageAccounts) —
    // dar chiar dacă ar ajunge, acțiunea e invizibilă. Verificăm gate-ul de acces.
    expect($teacher->canManageAccounts())->toBeFalse();
});
