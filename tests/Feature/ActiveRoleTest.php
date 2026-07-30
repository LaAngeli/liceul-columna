<?php

/**
 * F1 — ROLUL ACTIV (migrarea multi-rol, raport aprobat 30.07.2026).
 *
 * Regulile rezolvării ({@see ActiveRole}): mono-rol = rolul lui, întotdeauna;
 * multi-rol = sesiune (doar a utilizatorului autentificat) → preferință persistată → prioritatea
 * enum-ului. Fațada de capabilități răspunde după rolul ACTIV; accesul la aplicație rămâne pe
 * reuniune. Deciziile beneficiarului: comutarea e exclusiv staff; fără gardă anti-auto-aprobare.
 */

use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ActiveRole;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function multiRoleUser(string ...$roles): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        $user->assignRole($role);
    }

    return $user->fresh();
}

it('mono-rol: rolul activ e rolul contului, sesiunea nu contează', function () {
    $user = multiRoleUser(UserRole::Profesor->value);

    actingAs($user);
    session()->put(ActiveRole::SESSION_KEY, UserRole::Director->value);

    expect($user->activeRole())->toBe(UserRole::Profesor)
        ->and($user->isMultiRole())->toBeFalse();
});

it('multi-rol fără sesiune: câștigă prioritatea enum-ului (Director înaintea Profesorului)', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);

    expect($user->activeRole())->toBe(UserRole::Director)
        ->and($user->isMultiRole())->toBeTrue();
});

it('preferința persistată bate prioritatea; sesiunea le bate pe amândouă', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);
    $user->forceFill(['preferred_role' => UserRole::Profesor->value])->save();
    $user = $user->fresh();

    expect($user->activeRole())->toBe(UserRole::Profesor);

    actingAs($user);
    session()->put(ActiveRole::SESSION_KEY, UserRole::Director->value);

    expect($user->activeRole())->toBe(UserRole::Director);
});

it('sesiunea MEA nu se scurge peste contul inspectat al altcuiva', function () {
    $admin = multiRoleUser(UserRole::Admin->value);
    $target = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);

    actingAs($admin);
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);

    // Ținta se rezolvă pe regulile EI (prioritate), nu pe sesiunea admin-ului.
    expect(ActiveRole::resolve($target))->toBe(UserRole::Director);
});

it('o valoare de sesiune care nu-i aparține contului e ignorată', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Diriginte->value);

    actingAs($user);
    session()->put(ActiveRole::SESSION_KEY, UserRole::Director->value);

    expect($user->activeRole())->toBe(UserRole::Diriginte);
});

it('capabilitățile urmează rolul ACTIV: directorul-profesor își pierde puterile în context Profesor', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);
    actingAs($user);

    // Context implicit = Director (prioritate): autoritate academică deplină.
    expect($user->isAdministrator())->toBeTrue()
        ->and($user->canAdministerCatalog())->toBeTrue()
        ->and($user->canManageAccounts())->toBeTrue();

    // Comutat pe Profesor: puterile administrative dispar; accesul la panou rămâne (reuniune).
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);

    expect($user->isAdministrator())->toBeFalse()
        ->and($user->canAdministerCatalog())->toBeFalse()
        ->and($user->canManageAccounts())->toBeFalse()
        ->and($user->hasAnyRole(UserRole::panelRoleValues()))->toBeTrue();
});

it('activate() scrie sesiunea și preferința; refuză rolurile străine sau de familie', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Diriginte->value);
    actingAs($user);

    expect(ActiveRole::activate($user, UserRole::Profesor))->toBeTrue()
        ->and(session()->get(ActiveRole::SESSION_KEY))->toBe(UserRole::Profesor->value)
        ->and($user->fresh()->preferred_role)->toBe(UserRole::Profesor->value)
        // Rol pe care nu-l are → refuz.
        ->and(ActiveRole::activate($user, UserRole::Director))->toBeFalse();

    // Familia nu intră NICIODATĂ în comutator (decizia beneficiarului: fără switch în cabinet).
    $family = multiRoleUser(UserRole::Parinte->value);
    actingAs($family);

    expect(ActiveRole::activate($family, UserRole::Parinte))->toBeFalse()
        ->and($family->isMultiRole())->toBeFalse();
});

// ── F2: comutatorul din topbar ─────────────────────────────────────────────────────────────────

it('comutatorul schimbă contextul prin POST și persistă preferința', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);
    actingAs($user);

    $this->post(route('staff.role.switch'), ['role' => UserRole::Profesor->value])
        ->assertRedirect();

    expect(session()->get(ActiveRole::SESSION_KEY))->toBe(UserRole::Profesor->value)
        ->and($user->fresh()->preferred_role)->toBe(UserRole::Profesor->value);
});

it('comutatorul refuză rolurile străine contului', function () {
    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Diriginte->value);
    actingAs($user);

    $this->post(route('staff.role.switch'), ['role' => UserRole::Director->value])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(session()->get(ActiveRole::SESSION_KEY))->toBeNull();
});

it('familia nu poate folosi comutatorul — 403, cabinetul rămâne fără switch', function () {
    foreach ([UserRole::Elev, UserRole::Parinte] as $role) {
        $family = multiRoleUser($role->value);
        actingAs($family);

        $this->post(route('staff.role.switch'), ['role' => $role->value])
            ->assertForbidden();
    }
});

it('topbar-ul arată COMUTATOR (dropdown Filament) pentru multi-rol și badge static pentru mono-rol', function () {
    $multi = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);
    actingAs($multi);

    $html = view('filament.topbar.live-datetime')->render();

    // Trigger + panou cu câte un rând-formular per rol (POST, nu <select> nativ — acela avea
    // popup desenat de OS, nestilizabil) și starea activă marcată.
    expect($html)->toContain('id="fi-role-switch"')
        ->and($html)->toContain('fi-role-menu__item')
        ->and($html)->toContain('fi-role-menu__item--active')
        ->and($html)->toContain(UserRole::Director->label())
        ->and($html)->toContain(UserRole::Profesor->label());

    $mono = multiRoleUser(UserRole::Profesor->value);
    actingAs($mono);

    $html = view('filament.topbar.live-datetime')->render();

    expect($html)->not->toContain('id="fi-role-switch"')
        ->and($html)->toContain('fi-role-badge');
});

it('jurnalul consemnează rolul ACTIV al actorului multi-rol', function () {
    config(['audit.console' => true]);

    $user = multiRoleUser(UserRole::Profesor->value, UserRole::Director->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    actingAs($user->fresh());

    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);

    $subject = Subject::factory()->create();
    $subject->update(['name' => $subject->name.' (modificat)']);

    $entry = Audit::query()->where('event', 'updated')->latest('id')->firstOrFail();

    expect($entry->actor_role)->toBe(UserRole::Profesor->value);
});
