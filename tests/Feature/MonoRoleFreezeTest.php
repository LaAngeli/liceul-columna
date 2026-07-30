<?php

/**
 * GARDA F0 A MIGRĂRII MULTI-ROL (raport-multirole-context-audit.md, aprobat 30.07.2026).
 *
 * Matricea de mai jos e comportamentul EXACT al fațadei de capabilități pentru un cont cu UN
 * SINGUR rol, ÎNGHEȚAT înainte de introducerea rolului activ (generat mecanic din codul de la
 * `747cc88`, nu transcris de mână). Contractul migrării: pentru conturile mono-rol — adică toate
 * conturile existente — NIMIC nu se schimbă, în nicio fază. Orice roșu aici = scurgere de
 * permisiuni introdusă de migrare (riscul nr. 1 din documentul beneficiarului).
 *
 * NU actualiza valorile ca să treacă testul: dacă o schimbare e intenționată, ea trebuie făcută
 * explicit, cu decizia beneficiarului consemnată — exact ca la generarea inițială.
 */

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

const MONO_ROLE_MATRIX =
    [
        'admin' => [
            'isAdministrator' => true,
            'isSuperAdmin' => true,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => true,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => true,
            'canConfigureSchool' => true,
            'canManageFamilyAccounts' => true,
            'canManageAccounts' => true,
            'canPublishContent' => true,
            'canManageSchedules' => true,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => true,
            'canAdministerCatalog' => true,
            'canValidateSemester' => true,
            'canViewCorrectionArchive' => true,
            'canViewAuditLog' => true,
            'canManageInfrastructure' => true,
            'canManageCalendarEvents' => true,
            'canBackdateCalendarEvents' => true,
            'canSeeAcademicData' => true,
            'canManageDocuments' => true,
            'homePath' => 'admin',
            'manageableRoleValues' => [
                0 => 'admin',
                1 => 'administrator-operational',
                2 => 'administrator-tehnic',
                3 => 'director',
                4 => 'diriginte',
                5 => 'elev',
                6 => 'parinte',
                7 => 'prim-vicedirector',
                8 => 'profesor',
            ],
        ],
        'director' => [
            'isAdministrator' => true,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => true,
            'isManagement' => true,
            'canManageCorigenta' => true,
            'canConfigureSchool' => true,
            'canManageFamilyAccounts' => true,
            'canManageAccounts' => true,
            'canPublishContent' => true,
            'canManageSchedules' => false,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => true,
            'canAdministerCatalog' => true,
            'canValidateSemester' => true,
            'canViewCorrectionArchive' => true,
            'canViewAuditLog' => true,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => true,
            'canBackdateCalendarEvents' => true,
            'canSeeAcademicData' => true,
            'canManageDocuments' => true,
            'homePath' => 'admin',
            'manageableRoleValues' => [
                0 => 'administrator-operational',
                1 => 'director',
                2 => 'diriginte',
                3 => 'elev',
                4 => 'parinte',
                5 => 'prim-vicedirector',
                6 => 'profesor',
            ],
        ],
        'prim-vicedirector' => [
            'isAdministrator' => true,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => true,
            'isManagement' => true,
            'canManageCorigenta' => true,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => true,
            'canManageSchedules' => false,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => true,
            'canAdministerCatalog' => true,
            'canValidateSemester' => true,
            'canViewCorrectionArchive' => true,
            'canViewAuditLog' => true,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => true,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => true,
            'canManageDocuments' => false,
            'homePath' => 'admin',
            'manageableRoleValues' => [
            ],
        ],
        'administrator-operational' => [
            'isAdministrator' => true,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => true,
            'isSystemAdministrator' => false,
            'isDirector' => false,
            'isManagement' => true,
            'canManageCorigenta' => true,
            'canConfigureSchool' => true,
            'canManageFamilyAccounts' => true,
            'canManageAccounts' => true,
            'canPublishContent' => true,
            'canManageSchedules' => true,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => true,
            'canViewAuditLog' => true,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => true,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => true,
            'canManageDocuments' => true,
            'homePath' => 'admin',
            'manageableRoleValues' => [
                0 => 'diriginte',
                1 => 'elev',
                2 => 'parinte',
                3 => 'profesor',
            ],
        ],
        'administrator-tehnic' => [
            'isAdministrator' => false,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => true,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => true,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => false,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => false,
            'canManageSchedules' => false,
            'canViewSchedules' => false,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => false,
            'canViewAuditLog' => true,
            'canManageInfrastructure' => true,
            'canManageCalendarEvents' => false,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => false,
            'canManageDocuments' => false,
            'homePath' => 'admin',
            'manageableRoleValues' => [
            ],
        ],
        'diriginte' => [
            'isAdministrator' => false,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => false,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => false,
            'canManageSchedules' => false,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => false,
            'canViewAuditLog' => false,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => false,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => false,
            'canManageDocuments' => false,
            'homePath' => 'admin',
            'manageableRoleValues' => [
            ],
        ],
        'profesor' => [
            'isAdministrator' => false,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => false,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => false,
            'canManageSchedules' => false,
            'canViewSchedules' => true,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => false,
            'canViewAuditLog' => false,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => false,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => false,
            'canManageDocuments' => false,
            'homePath' => 'admin',
            'manageableRoleValues' => [
            ],
        ],
        'elev' => [
            'isAdministrator' => false,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => false,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => false,
            'canManageSchedules' => false,
            'canViewSchedules' => false,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => false,
            'canViewAuditLog' => false,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => false,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => false,
            'canManageDocuments' => false,
            'homePath' => 'dashboard',
            'manageableRoleValues' => [
            ],
        ],
        'parinte' => [
            'isAdministrator' => false,
            'isSuperAdmin' => false,
            'isTechnicalAdmin' => false,
            'isOperationalAdmin' => false,
            'isSystemAdministrator' => false,
            'isDirector' => false,
            'isManagement' => false,
            'canManageCorigenta' => false,
            'canConfigureSchool' => false,
            'canManageFamilyAccounts' => false,
            'canManageAccounts' => false,
            'canPublishContent' => false,
            'canManageSchedules' => false,
            'canViewSchedules' => false,
            'canApproveGradeCorrections' => false,
            'canAdministerCatalog' => false,
            'canValidateSemester' => false,
            'canViewCorrectionArchive' => false,
            'canViewAuditLog' => false,
            'canManageInfrastructure' => false,
            'canManageCalendarEvents' => false,
            'canBackdateCalendarEvents' => false,
            'canSeeAcademicData' => false,
            'canManageDocuments' => false,
            'homePath' => 'dashboard',
            'manageableRoleValues' => [
            ],
        ],
    ];

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('contul mono-rol păstrează exact capabilitățile înghețate', function (string $roleValue) {
    $user = User::factory()->create();
    $user->assignRole($roleValue);
    $user = $user->fresh();

    $frozen = MONO_ROLE_MATRIX[$roleValue];

    foreach ($frozen as $method => $expected) {
        if ($method === 'homePath') {
            expect(str_contains($user->homePath(), '/admin') ? 'admin' : 'dashboard')
                ->toBe($expected, "homePath diferă pentru rolul {$roleValue}");

            continue;
        }

        if ($method === 'manageableRoleValues') {
            $actual = $user->manageableRoleValues();
            sort($actual);
            expect($actual)->toBe($expected, "manageableRoleValues diferă pentru rolul {$roleValue}");

            continue;
        }

        expect($user->{$method}())->toBe($expected, "{$method}() diferă pentru rolul {$roleValue}");
    }
})->with(array_keys(MONO_ROLE_MATRIX));
