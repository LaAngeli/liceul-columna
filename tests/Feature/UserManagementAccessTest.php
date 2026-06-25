<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function userCu(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('administrația poate accesa gestiunea utilizatorilor', function (UserRole $role) {
    $this->actingAs(userCu($role));
    expect(UserResource::canAccess())->toBeTrue();
})->with([UserRole::Admin, UserRole::Director, UserRole::DirectorAdjunct]);

it('restul personalului și elevii/părinții NU pot gestiona utilizatori', function (UserRole $role) {
    $this->actingAs(userCu($role));
    expect(UserResource::canAccess())->toBeFalse();
})->with([UserRole::Profesor, UserRole::Diriginte, UserRole::Elev, UserRole::Parinte]);

it('rolurile atribuibile respectă ierarhia', function () {
    expect(userCu(UserRole::Admin)->manageableRoleValues())->toContain('admin', 'director', 'director-adjunct')
        ->and(userCu(UserRole::Director)->manageableRoleValues())
        ->toContain('director', 'profesor', 'elev')->not->toContain('admin')
        ->and(userCu(UserRole::DirectorAdjunct)->manageableRoleValues())
        ->toContain('profesor', 'elev')->not->toContain('admin', 'director');
});

it('adminul poate gestiona orice cont', function () {
    $admin = userCu(UserRole::Admin);
    expect($admin->canManageUser(userCu(UserRole::Admin)))->toBeTrue()
        ->and($admin->canManageUser(userCu(UserRole::Director)))->toBeTrue()
        ->and($admin->canManageUser(userCu(UserRole::Elev)))->toBeTrue();
});

it('directorul gestionează pe oricine în afară de admin', function () {
    $director = userCu(UserRole::Director);
    expect($director->canManageUser(userCu(UserRole::Director)))->toBeTrue()
        ->and($director->canManageUser(userCu(UserRole::Profesor)))->toBeTrue()
        ->and($director->canManageUser(userCu(UserRole::Admin)))->toBeFalse();
});

it('directorul-adjunct gestionează pe oricine în afară de admin și director', function () {
    $adjunct = userCu(UserRole::DirectorAdjunct);
    expect($adjunct->canManageUser(userCu(UserRole::Profesor)))->toBeTrue()
        ->and($adjunct->canManageUser(userCu(UserRole::DirectorAdjunct)))->toBeTrue()
        ->and($adjunct->canManageUser(userCu(UserRole::Admin)))->toBeFalse()
        ->and($adjunct->canManageUser(userCu(UserRole::Director)))->toBeFalse();
});

it('UserResource::canEdit respectă ierarhia (directorul nu editează un admin)', function () {
    $admin = userCu(UserRole::Admin);
    $this->actingAs(userCu(UserRole::Director));

    expect(UserResource::canEdit($admin))->toBeFalse();

    $this->actingAs(userCu(UserRole::Admin));
    expect(UserResource::canEdit($admin))->toBeTrue();
});
