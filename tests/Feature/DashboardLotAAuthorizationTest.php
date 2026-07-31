<?php

use App\Enums\UserRole;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\ConsentAcknowledgments\ConsentAcknowledgmentResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
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

// ─── C-1: conturile NU se șterg din panou (decizia beneficiarului 2026-07-23) ───────────
//
// Testele anterioare pineau ierarhia bulk-delete-ului (directorul să nu poată șterge un
// super-admin). Ștergerea de conturi a fost ELIMINATĂ din panou — `User` n-are soft delete, deci
// era definitivă și lua cu ea legăturile părinte–copil prin cascada FK. Intenția de securitate se
// păstrează, dar acum e garantată mai tare: nimeni nu poate șterge niciun cont, indiferent de rol.
// Reversibilul e suspendarea; ștergerea legală trece prin `app:delete-account` (consolă).

it('Utilizatori: nu mai există ștergere în masă — acțiunea a dispărut din tabel', function () {
    actingAs(lotARoleUser(UserRole::Director->value));

    Livewire::test(ListUsers::class)->assertTableBulkActionDoesNotExist('delete');
});

it('nimeni nu poate șterge un cont din panou — nici directorul, nici super-adminul', function () {
    $parinte = lotARoleUser(UserRole::Parinte->value);
    $tehnic = lotARoleUser(UserRole::AdministratorTehnic->value);

    foreach ([UserRole::Director, UserRole::Admin] as $role) {
        actingAs(lotARoleUser($role->value));

        expect(UserResource::canDelete($parinte))->toBeFalse()
            ->and(UserResource::canDelete($tehnic))->toBeFalse()
            ->and(UserResource::canDeleteAny())->toBeFalse();
    }

    // Conturile rămân intacte: nicio cale din panou nu le atinge.
    expect(User::find($parinte->id))->not->toBeNull()
        ->and(User::find($tehnic->id))->not->toBeNull();
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

// ─── M-6: fișele de profesor = configurare (consolidate în Utilizatori, 2026-07-31) ─────

it('fișa profesională se administrează din Utilizatori: PVD fără secțiune; AO cu tot fluxul', function () {
    // Consolidarea 2026-07-31: registrul „Profesori" nu mai există — fișa (identitate, alocări,
    // dirigenție, arhivare) trăiește pe fișa PERSOANEI din Utilizatori, gate-uită pe
    // canManageAccounts (super/dir/AO). Prim-vicedirectorul nu gestionează personal.
    actingAs(lotARoleUser(UserRole::PrimVicedirector->value));
    expect(UserResource::canAccess())->toBeFalse();

    actingAs(lotARoleUser(UserRole::AdministratorOperational->value));
    expect(UserResource::canAccess())->toBeTrue()
        // Arhivarea fișei rămâne un act de CONFIGURARE (canConfigureSchool) — AO îl are.
        ->and(auth('web')->user()?->canConfigureSchool())->toBeTrue();
});
