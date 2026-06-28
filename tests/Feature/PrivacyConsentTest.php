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

it('o versiune nouă a notei cere reconfirmare', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'privacy_acknowledged_version' => 'old-version',
    ]);
    $user->assignRole(UserRole::Elev->value);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('privacy.consent'));
});
