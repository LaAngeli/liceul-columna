<?php

use App\Enums\UserRole;
use App\Models\ConsentAcknowledgment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    config(['privacy.notice_version' => 'test-v1']);
});

it('elevul fără confirmare e redirecționat la nota de informare', function () {
    $user = User::factory()->unacknowledged()->create(['must_change_password' => false]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('privacy.consent'));
});

it('confirmarea înregistrează versiunea + IP-ul și deblochează cabinetul', function () {
    $user = User::factory()->unacknowledged()->create(['must_change_password' => false]);
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->post(route('privacy.consent.store'))->assertRedirect();

    $user->refresh();
    expect($user->privacy_acknowledged_version)->toBe('test-v1')
        ->and($user->hasAcknowledgedCurrentPrivacyNotice())->toBeTrue()
        ->and(ConsentAcknowledgment::query()
            ->where('user_id', $user->id)
            ->where('document_version', 'test-v1')
            ->exists())->toBeTrue();

    // Gate-ul e ridicat: ajunge în cabinet.
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('personalul NU e blocat de nota de informare', function () {
    $staff = User::factory()->create(['must_change_password' => false]);
    $staff->assignRole(UserRole::Profesor->value);

    // Redirecționat de garda cabinetului spre panou (/admin), NU spre consimțământ.
    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect('/admin');
});

it('confirmarea duce ACASĂ, nu la ținta rămasă în sesiune de la un oaspete', function () {
    // Regresie 07.08.2026 („403 doar pentru elevii noi creați"): browserul atinsese /admin
    // neautentificat, Laravel reținuse `url.intended = /admin`, iar confirmarea notei — ultimul pas
    // al onboardingului — o consuma cu `redirect()->intended()` și trimitea elevul în panou. 403 la
    // prima intrare, dispărut la refresh, fiindcă ținta se consumă o singură dată.
    $user = User::factory()->unacknowledged()->create(['must_change_password' => false]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/admin')])
        ->post(route('privacy.consent.store'))
        ->assertRedirect(route('dashboard'));
});

it('logarea UITĂ ținta de oaspete, ca să n-o culeagă un pas de mai târziu', function () {
    // Fixul sistemic: LoginResponse ignora deliberat `intended`, dar îl lăsa în sesiune — o mină
    // pentru orice `intended()` de mai încolo. Acum se stinge la logare.
    $user = User::factory()->create([
        'must_change_password' => false,
        'privacy_acknowledged_version' => 'test-v1',
        'password' => 'ParolaTest123!',
    ]);
    $user->assignRole(UserRole::Elev->value);

    $this->withSession(['url.intended' => url('/admin')])
        ->post(route('login'), ['email' => $user->email, 'password' => 'ParolaTest123!'])
        ->assertRedirect(route('dashboard'));

    expect(session()->has('url.intended'))->toBeFalse();
});

it('o versiune nouă a notei cere reconfirmare', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'privacy_acknowledged_version' => 'old-version',
    ]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('privacy.consent'));
});
