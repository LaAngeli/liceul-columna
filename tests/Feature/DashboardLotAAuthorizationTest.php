<?php

use App\Enums\UserRole;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\ConsentAcknowledgments\ConsentAcknowledgmentResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function lotARoleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

// ─── C-1: bulk-delete Utilizatori respectă ierarhia manageableRoleValues ────────────────

it('bulk-delete Utilizatori: directorul NU șterge super-admin/AT/propriul cont; șterge rolurile administrabile', function () {
    $director = lotARoleUser(UserRole::Director->value);
    $super = lotARoleUser(UserRole::Admin->value);
    $tehnic = lotARoleUser(UserRole::AdministratorTehnic->value);
    $parinte = lotARoleUser(UserRole::Parinte->value);

    actingAs($director);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', [$super, $tehnic, $parinte, $director]);

    expect(User::find($super->id))->not->toBeNull()      // super-admin break-glass protejat
        ->and(User::find($tehnic->id))->not->toBeNull()  // AT în afara ierarhiei directorului
        ->and(User::find($director->id))->not->toBeNull() // propriul cont
        ->and(User::find($parinte->id))->toBeNull();      // rol administrabil → șters
});

it('bulk-delete Utilizatori: super-adminul șterge orice cont, dar nu al său', function () {
    $super = lotARoleUser(UserRole::Admin->value);
    $other = lotARoleUser(UserRole::Admin->value);

    actingAs($super);

    Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$other, $super]);

    expect(User::find($other->id))->toBeNull()
        ->and(User::find($super->id))->not->toBeNull();
});

// ─── Î-1: registrul de consimțăminte (PII minori) ascuns administratorului tehnic ───────

it('consimțămintele (PII) sunt ascunse administratorului tehnic, vizibile administrației', function () {
    actingAs(lotARoleUser(UserRole::AdministratorTehnic->value));
    expect(ConsentAcknowledgmentResource::canViewAny())->toBeFalse();

    actingAs(lotARoleUser(UserRole::Director->value));
    expect(ConsentAcknowledgmentResource::canViewAny())->toBeTrue();
});

// ─── Î-2: administratorul tehnic exclus din catalogul academic ──────────────────────────

it('administratorul tehnic NU vede resursele academice de catalog (nici Calendarul)', function () {
    actingAs(lotARoleUser(UserRole::AdministratorTehnic->value));

    expect(GradeResource::canViewAny())->toBeFalse()
        ->and(StudentResource::canAccess())->toBeFalse()
        ->and(Calendar::canAccess())->toBeFalse();
});

it('profesorul (cu fișă) vede catalogul academic scoped', function () {
    $user = lotARoleUser(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    expect(GradeResource::canViewAny())->toBeTrue()
        ->and(StudentResource::canAccess())->toBeTrue();
});

// ─── Î-4: ForceDelete/Restore în masă pe configurare gate pe canConfigureSchool ─────────

it('prim-vicedirectorul NU poate ForceDelete/Restore resurse de configurare', function () {
    actingAs(lotARoleUser(UserRole::PrimVicedirector->value));
    expect(AcademicYearResource::canForceDeleteAny())->toBeFalse()
        ->and(AcademicYearResource::canRestoreAny())->toBeFalse();

    actingAs(lotARoleUser(UserRole::AdministratorOperational->value));
    expect(AcademicYearResource::canForceDeleteAny())->toBeTrue()
        ->and(AcademicYearResource::canRestoreAny())->toBeTrue();
});

// ─── M-6: fișele de profesor = configurare (nu prim-vicedirector) ───────────────────────

it('fișele de profesor: prim-vicedirectorul le vede dar NU le editează; AO da', function () {
    // Onboarding unificat (2026-07-16): fișa NU se mai creează separat — canCreate e închis
    // pentru TOȚI (fluxul trece prin Utilizatori). Configurarea = editarea fișei existente.
    $teacher = Teacher::factory()->create();

    actingAs(lotARoleUser(UserRole::PrimVicedirector->value));
    expect(TeacherResource::canAccess())->toBeTrue()   // vede (isAdministrator)
        ->and(TeacherResource::canCreate())->toBeFalse()
        ->and(TeacherResource::canEdit($teacher))->toBeFalse(); // dar nu configurează

    actingAs(lotARoleUser(UserRole::AdministratorOperational->value));
    expect(TeacherResource::canCreate())->toBeFalse()
        ->and(TeacherResource::canEdit($teacher))->toBeTrue();
});
