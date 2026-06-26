<?php

use App\Enums\UserRole;
use App\Filament\Resources\Grades\GradeResource;
use App\Models\Grade;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function actor(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('isAdministrator (vede tot catalogul) = academia, FĂRĂ administratorul tehnic', function () {
    expect(actor(UserRole::Admin)->isAdministrator())->toBeTrue()
        ->and(actor(UserRole::Director)->isAdministrator())->toBeTrue()
        ->and(actor(UserRole::PrimVicedirector)->isAdministrator())->toBeTrue()
        ->and(actor(UserRole::AdministratorOperational)->isAdministrator())->toBeTrue()
        ->and(actor(UserRole::AdministratorTehnic)->isAdministrator())->toBeFalse()
        ->and(actor(UserRole::Profesor)->isAdministrator())->toBeFalse();
});

it('canConfigureSchool: super-admin / director / administrator operațional', function () {
    expect(actor(UserRole::Admin)->canConfigureSchool())->toBeTrue()
        ->and(actor(UserRole::Director)->canConfigureSchool())->toBeTrue()
        ->and(actor(UserRole::AdministratorOperational)->canConfigureSchool())->toBeTrue()
        ->and(actor(UserRole::PrimVicedirector)->canConfigureSchool())->toBeFalse()
        ->and(actor(UserRole::AdministratorTehnic)->canConfigureSchool())->toBeFalse()
        ->and(actor(UserRole::Profesor)->canConfigureSchool())->toBeFalse();
});

it('canApproveGradeCorrections: super-admin / director / prim-vicedirector (NU AO)', function () {
    expect(actor(UserRole::Admin)->canApproveGradeCorrections())->toBeTrue()
        ->and(actor(UserRole::Director)->canApproveGradeCorrections())->toBeTrue()
        ->and(actor(UserRole::PrimVicedirector)->canApproveGradeCorrections())->toBeTrue()
        ->and(actor(UserRole::AdministratorOperational)->canApproveGradeCorrections())->toBeFalse()
        ->and(actor(UserRole::AdministratorTehnic)->canApproveGradeCorrections())->toBeFalse();
});

it('canManageInfrastructure: doar super-admin și administrator tehnic', function () {
    expect(actor(UserRole::Admin)->canManageInfrastructure())->toBeTrue()
        ->and(actor(UserRole::AdministratorTehnic)->canManageInfrastructure())->toBeTrue()
        ->and(actor(UserRole::Director)->canManageInfrastructure())->toBeFalse()
        ->and(actor(UserRole::AdministratorOperational)->canManageInfrastructure())->toBeFalse();
});

it('canViewAuditLog: administrația academică + administratorul tehnic; NU personalul/familia', function () {
    expect(actor(UserRole::Admin)->canViewAuditLog())->toBeTrue()
        ->and(actor(UserRole::Director)->canViewAuditLog())->toBeTrue()
        ->and(actor(UserRole::PrimVicedirector)->canViewAuditLog())->toBeTrue()
        ->and(actor(UserRole::AdministratorOperational)->canViewAuditLog())->toBeTrue()
        ->and(actor(UserRole::AdministratorTehnic)->canViewAuditLog())->toBeTrue()
        ->and(actor(UserRole::Profesor)->canViewAuditLog())->toBeFalse()
        ->and(actor(UserRole::Parinte)->canViewAuditLog())->toBeFalse();
});

it('administratorul operațional NU editează catalogul, nici aprobă corecții — dar VEDE tot (§3.2)', function () {
    $ao = actor(UserRole::AdministratorOperational);

    expect($ao->canAdministerCatalog())->toBeFalse()
        ->and($ao->canApproveGradeCorrections())->toBeFalse()
        ->and($ao->isAdministrator())->toBeTrue()
        ->and($ao->canViewCorrectionArchive())->toBeTrue()
        ->and($ao->canConfigureSchool())->toBeTrue();
});

it('administratorul tehnic NU are acces academic (doar infrastructură, §3.2)', function () {
    $at = actor(UserRole::AdministratorTehnic);

    expect($at->isAdministrator())->toBeFalse()
        ->and($at->canAdministerCatalog())->toBeFalse()
        ->and($at->canConfigureSchool())->toBeFalse()
        ->and($at->canManageAccounts())->toBeFalse()
        ->and($at->canManageInfrastructure())->toBeTrue()
        ->and($at->isSystemAdministrator())->toBeTrue();
});

it('GradeResource::canCreate: profesori + autoritate academică; NU administratorul operațional/tehnic', function () {
    $this->actingAs(actor(UserRole::AdministratorOperational));
    expect(GradeResource::canCreate())->toBeFalse();

    $this->actingAs(actor(UserRole::AdministratorTehnic));
    expect(GradeResource::canCreate())->toBeFalse();

    $this->actingAs(actor(UserRole::Director));
    expect(GradeResource::canCreate())->toBeTrue();

    $prof = actor(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $prof->id]);
    $this->actingAs($prof);
    expect(GradeResource::canCreate())->toBeTrue();
});

it('administratorul tehnic nu vede note în panou; administratorul operațional le vede pe toate', function () {
    Grade::factory()->count(2)->create();

    $this->actingAs(actor(UserRole::AdministratorTehnic));
    expect(GradeResource::getEloquentQuery()->count())->toBe(0);

    $this->actingAs(actor(UserRole::AdministratorOperational));
    expect(GradeResource::getEloquentQuery()->count())->toBe(2);
});
