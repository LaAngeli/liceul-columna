<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
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

it('doar rolurile care atribuie conturi pot accesa gestiunea utilizatorilor', function (UserRole $role) {
    $this->actingAs(userCu($role));
    expect(UserResource::canAccess())->toBeTrue();
})->with([UserRole::Admin, UserRole::Director, UserRole::AdministratorOperational]);

it('restul rolurilor NU pot gestiona utilizatori', function (UserRole $role) {
    $this->actingAs(userCu($role));
    expect(UserResource::canAccess())->toBeFalse();
})->with([
    UserRole::PrimVicedirector,
    UserRole::AdministratorTehnic,
    UserRole::Profesor,
    UserRole::Diriginte,
    UserRole::Elev,
    UserRole::Parinte,
]);

it('rolurile atribuibile respectă ierarhia', function () {
    expect(userCu(UserRole::Admin)->manageableRoleValues())
        ->toContain('admin', 'director', 'prim-vicedirector', 'administrator-operational', 'administrator-tehnic')
        ->and(userCu(UserRole::Director)->manageableRoleValues())
        ->toContain('director', 'prim-vicedirector', 'administrator-operational', 'profesor', 'elev')
        ->not->toContain('admin', 'administrator-tehnic')
        ->and(userCu(UserRole::AdministratorOperational)->manageableRoleValues())
        ->toContain('profesor', 'diriginte', 'elev', 'parinte')
        ->not->toContain('admin', 'director', 'administrator-operational', 'prim-vicedirector', 'administrator-tehnic');
});

it('super-adminul poate gestiona orice cont', function () {
    $admin = userCu(UserRole::Admin);
    expect($admin->canManageUser(userCu(UserRole::Admin)))->toBeTrue()
        ->and($admin->canManageUser(userCu(UserRole::Director)))->toBeTrue()
        ->and($admin->canManageUser(userCu(UserRole::AdministratorTehnic)))->toBeTrue()
        ->and($admin->canManageUser(userCu(UserRole::Elev)))->toBeTrue();
});

it('directorul gestionează pe oricine în afară de super-admin și administrator tehnic', function () {
    $director = userCu(UserRole::Director);
    expect($director->canManageUser(userCu(UserRole::AdministratorOperational)))->toBeTrue()
        ->and($director->canManageUser(userCu(UserRole::Profesor)))->toBeTrue()
        ->and($director->canManageUser(userCu(UserRole::Admin)))->toBeFalse()
        ->and($director->canManageUser(userCu(UserRole::AdministratorTehnic)))->toBeFalse();
});

it('administratorul operațional gestionează doar conturi de familie + personal pedagogic', function () {
    $ao = userCu(UserRole::AdministratorOperational);
    expect($ao->canManageUser(userCu(UserRole::Profesor)))->toBeTrue()
        ->and($ao->canManageUser(userCu(UserRole::Elev)))->toBeTrue()
        ->and($ao->canManageUser(userCu(UserRole::Director)))->toBeFalse()
        ->and($ao->canManageUser(userCu(UserRole::AdministratorOperational)))->toBeFalse()
        ->and($ao->canManageUser(userCu(UserRole::Admin)))->toBeFalse();
});

it('prim-vicedirectorul și administratorul tehnic NU gestionează conturi', function () {
    expect(userCu(UserRole::PrimVicedirector)->canManageUser(userCu(UserRole::Profesor)))->toBeFalse()
        ->and(userCu(UserRole::AdministratorTehnic)->canManageUser(userCu(UserRole::Elev)))->toBeFalse();
});

it('UserResource::canEdit respectă ierarhia (directorul nu editează un super-admin)', function () {
    $admin = userCu(UserRole::Admin);
    $this->actingAs(userCu(UserRole::Director));

    expect(UserResource::canEdit($admin))->toBeFalse();

    $this->actingAs(userCu(UserRole::Admin));
    expect(UserResource::canEdit($admin))->toBeTrue();
});

it('administrația poate reseta parola unui elev fără e-mail, fără să fie forțată să adauge unul', function () {
    $admin = userCu(UserRole::Admin);
    $elev = User::factory()->create(['email' => null, 'username' => 'elev.fara.email']);
    $elev->assignRole(UserRole::Elev->value);

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $elev->getRouteKey()])
        ->fillForm(['password' => 'parola-noua-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    $elev->refresh();
    expect($elev->email)->toBeNull()
        ->and(Hash::check('parola-noua-123', $elev->password))->toBeTrue();
});
