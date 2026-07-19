<?php

/**
 * REZIDUUL DE PRIVILEGIU la retrogradare (LOT 2 al restructurării „Configurare").
 *
 * `audience_domains` (responsabilul unui domeniu: Instruire/Educație) e afișat în formularul de
 * utilizator DOAR pentru rolurile de conducere. Filament nu dehidratează componentele ascunse,
 * așa că la retrogradarea unui director la profesor coloana rămânea populată — iar consecințele
 * erau reale, nu teoretice: `canManageCorigenta()` rămânea true, iar rutările interoghează coloana
 * direct în SQL, deci o semnalare comportamentală despre un MINOR putea ateriza la un cont care
 * nu mai are dreptul s-o vadă (§4.2 / L133).
 *
 * Apărarea e pe DOUĂ straturi: curățarea coloanei la schimbarea rolului ȘI filtrul de rol în
 * interogări (un rând rămas dintr-un import sau dintr-o scriere directă nu trebuie să conteze).
 */

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    actingAs($this->admin);
});

it('retrogradarea golește domeniile de audiență și stinge dreptul derivat din ele', function () {
    // `username` e obligatoriu în formular, iar factory-ul nu-l setează.
    $user = User::factory()->create([
        'username' => 'dpopescu',
        'audience_domains' => [AudienceDomain::Instruire->value],
    ]);
    $user->assignRole(UserRole::Director->value);

    expect($user->canManageCorigenta())->toBeTrue();

    // Fișa e obligatorie pentru un cont pedagogic (onboarding unificat).
    $teacher = Teacher::factory()->create(['user_id' => null]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'link',
            'teacher_id' => $teacher->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->audience_domains)->toBeNull()
        ->and($user->canManageCorigenta())->toBeFalse()
        ->and($user->handlesAudienceDomain(AudienceDomain::Instruire))->toBeFalse();
});

it('un rând rezidual în coloană NU face contul destinatar: filtrul de rol e al doilea strat', function () {
    // Scriere DIRECTĂ în coloană (ocolind formularul) pe un cont fără rol de conducere —
    // simulează un rând rămas dintr-un import sau dintr-o migrare veche.
    $rezidual = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $rezidual->assignRole(UserRole::Profesor->value);

    $legitim = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $legitim->assignRole(UserRole::PrimVicedirector->value);

    $alesi = User::query()->handlingAudienceDomain(AudienceDomain::Educatie)->pluck('id')->all();

    expect($alesi)->toContain($legitim->id)
        ->and($alesi)->not->toContain($rezidual->id);

    // Și capabilitatea de instanță respectă aceeași regulă.
    expect($rezidual->handlesAudienceDomain(AudienceDomain::Educatie))->toBeFalse()
        ->and($legitim->handlesAudienceDomain(AudienceDomain::Educatie))->toBeTrue();
});

it('semnalarea comportamentală despre un minor nu ajunge la contul retrogradat', function () {
    // Contul retrogradat are id MIC → ar fi fost ales primul de `orderBy('id')`.
    $rezidual = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $rezidual->assignRole(UserRole::Profesor->value);

    $responsabil = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $responsabil->assignRole(UserRole::PrimVicedirector->value);

    // Profesorul care predă elevului semnalează comportamentul.
    $student = Student::factory()->create();

    $tinta = User::query()
        ->handlingAudienceDomain(AudienceDomain::Educatie)
        ->orderBy('id')
        ->first();

    expect($tinta?->id)->toBe($responsabil->id)
        ->and($student)->not->toBeNull();
});
