<?php

use App\Enums\UserRole;
use App\Filament\Widgets\AdminOverview;
use App\Filament\Widgets\DirectorOverview;
use App\Filament\Widgets\TeacherOverview;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function userWithRole(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('AdminOverview (sistem) e vizibil rolurilor de sistem (super-admin + administrator tehnic)', function (UserRole $role, bool $visible) {
    $this->actingAs(userWithRole($role));

    expect(AdminOverview::canView())->toBe($visible);
})->with([
    [UserRole::Admin, true],
    [UserRole::AdministratorTehnic, true],
    [UserRole::Director, false],
    [UserRole::PrimVicedirector, false],
    [UserRole::AdministratorOperational, false],
    [UserRole::Diriginte, false],
    [UserRole::Profesor, false],
]);

it('DirectorOverview (conducere) e vizibil conducerii și administratorului operațional', function (UserRole $role, bool $visible) {
    $this->actingAs(userWithRole($role));

    expect(DirectorOverview::canView())->toBe($visible);
})->with([
    [UserRole::Director, true],
    [UserRole::PrimVicedirector, true],
    [UserRole::AdministratorOperational, true],
    [UserRole::Admin, false],
    [UserRole::AdministratorTehnic, false],
    [UserRole::Diriginte, false],
    [UserRole::Profesor, false],
]);

it('TeacherOverview e vizibil profesorului/dirigintelui cu fișă, nu conducerii', function () {
    $prof = userWithRole(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $prof->id]);
    $this->actingAs($prof);
    expect(TeacherOverview::canView())->toBeTrue();

    $diriginte = userWithRole(UserRole::Diriginte);
    Teacher::factory()->create(['user_id' => $diriginte->id]);
    $this->actingAs($diriginte);
    expect(TeacherOverview::canView())->toBeTrue();

    $this->actingAs(userWithRole(UserRole::Director));
    expect(TeacherOverview::canView())->toBeFalse();

    $this->actingAs(userWithRole(UserRole::AdministratorOperational));
    expect(TeacherOverview::canView())->toBeFalse();

    $this->actingAs(userWithRole(UserRole::Admin));
    expect(TeacherOverview::canView())->toBeFalse();
});

it('helperii de rol disting rolurile de sistem de conducere', function () {
    expect(userWithRole(UserRole::Admin)->isSuperAdmin())->toBeTrue()
        ->and(userWithRole(UserRole::Admin)->isDirector())->toBeFalse()
        ->and(userWithRole(UserRole::AdministratorTehnic)->isTechnicalAdmin())->toBeTrue()
        ->and(userWithRole(UserRole::AdministratorTehnic)->isAdministrator())->toBeFalse()
        ->and(userWithRole(UserRole::Director)->isDirector())->toBeTrue()
        ->and(userWithRole(UserRole::Director)->isSuperAdmin())->toBeFalse()
        ->and(userWithRole(UserRole::PrimVicedirector)->isDirector())->toBeTrue()
        ->and(userWithRole(UserRole::AdministratorOperational)->isManagement())->toBeTrue();
});

it('un profesor cu fișă poate deschide /admin (widget-ul se randează)', function () {
    $prof = userWithRole(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $prof->id]);

    $this->actingAs($prof)->get('/admin')->assertOk();
});
