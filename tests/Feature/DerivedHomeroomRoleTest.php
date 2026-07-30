<?php

/**
 * MEMBRIA rolului „Diriginte", derivată din desemnare (reconvertit pentru multi-rol, 30.07.2026).
 *
 * Istoric: „rolul derivat" ÎNLOCUIA eticheta (profesor↔diriginte) — corect în lumea
 * un-cont-un-rol, dar distructiv sub multi-rol: crearea unei clase cu diriginte îi ștergea
 * persoanei rolul Profesor (prins de ContextSeparationTest în F3). Semantica nouă: dirigenția
 * ADAUGĂ rolul Diriginte pe lângă ce există; pierderea ultimei clase îl RETRAGE (mono-dirigintele
 * revine „Profesor" — contul nu rămâne fără rol). Conducerea și familia nu sunt atinse.
 *
 * Plus reparația păstrată din prima versiune: matricea de notificări urmează DESEMNAREA, nu doar
 * rolul — cine are dirigenție primește tipurile de diriginte în Setări.
 */

use App\Actions\SyncHomeroomRole;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
});

/** Cont de personal didactic cu fișă proprie. */
function derivedTeacher(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Teacher::factory()->create(['user_id' => $user->id]);

    return $user->fresh();
}

it('primirea unei clase ADAUGĂ rolul Diriginte lângă Profesor — nu îl înlocuiește', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $class = SchoolClass::factory()->for($this->year)->create();

    expect($user->getRoleNames()->all())->toBe([UserRole::Profesor->value]);

    $class->update(['homeroom_teacher_id' => $user->teacher->id]);

    expect($user->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value])
        // A devenit MULTI-rol: comutatorul de context i se deschide.
        ->and($user->fresh()->isMultiRole())->toBeTrue();
});

it('pierderea ultimei clase RETRAGE rolul Diriginte; profesorul rămâne profesor', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    expect($user->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value]);

    $class->update(['homeroom_teacher_id' => null]);

    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value]);
});

it('mono-dirigintele care pierde ultima clasă revine „Profesor" — contul nu rămâne fără rol', function () {
    $user = derivedTeacher(UserRole::Diriginte->value);
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    $class->update(['homeroom_teacher_id' => null]);

    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value]);
});

it('membria rămâne cât timp mai există măcar o clasă', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $first = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);
    SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    $first->update(['homeroom_teacher_id' => null]);

    expect($user->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value]);
});

it('reatribuirea mută membria la AMBELE capete', function () {
    $cedent = derivedTeacher(UserRole::Diriginte->value);
    $primitor = derivedTeacher(UserRole::Profesor->value);

    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $cedent->teacher->id]);

    $class->update(['homeroom_teacher_id' => $primitor->teacher->id]);

    expect($cedent->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value])
        ->and($primitor->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value]);
});

it('ștergerea clasei retrage membria, restaurarea o readuce', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    $class->delete();
    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value]);

    $class->restore();
    expect($user->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value]);
});

it('NU atinge conducerea care primește o clasă — dirigenția e o funcție în plus', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $teacher = Teacher::factory()->create(['user_id' => $director->id]);

    SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $teacher->id]);

    expect($director->fresh()->getRoleNames()->all())->toBe([UserRole::Director->value]);
});

it('sincronizează starea scrisă pe lângă observeri (importuri)', function () {
    $orfan = derivedTeacher(UserRole::Diriginte->value);
    $ascuns = derivedTeacher(UserRole::Profesor->value);

    $class = SchoolClass::factory()->for($this->year)->create();
    SchoolClass::query()->whereKey($class->id)->update(['homeroom_teacher_id' => $ascuns->teacher->id]);

    $sync = app(SyncHomeroomRole::class);

    expect($sync->drifted())->toHaveCount(2);

    foreach ($sync->drifted() as $row) {
        $sync->forUser($row['user']);
    }

    expect($orfan->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value])
        ->and($ascuns->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Profesor->value, UserRole::Diriginte->value])
        ->and(app(SyncHomeroomRole::class)->drifted())->toBe([]);
});

it('comanda raportează fără să scrie, iar cu --apply scrie', function () {
    $orfan = derivedTeacher(UserRole::Diriginte->value);

    $this->artisan('app:sync-homeroom-roles')->assertSuccessful();
    expect($orfan->fresh()->getRoleNames()->all())->toBe([UserRole::Diriginte->value]);

    $this->artisan('app:sync-homeroom-roles', ['--apply' => true])->assertSuccessful();
    expect($orfan->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value]);

    $this->artisan('app:sync-homeroom-roles')
        ->expectsOutputToContain('Nimic de sincronizat')
        ->assertSuccessful();
});

// ── Matricea de notificări urmează desemnarea ──────────────────────────────────────────────────

it('dă tipurile de diriginte celui DESEMNAT, chiar dacă rolurile contului nu includ „Diriginte"', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $teacher = Teacher::factory()->create(['user_id' => $director->id]);

    expect($director->fresh()->availableNotificationTypes())
        ->not->toContain(NotificationType::AbsenceMotivationSubmitted);

    SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $teacher->id]);

    expect($director->fresh()->availableNotificationTypes())
        ->toContain(NotificationType::AbsenceMotivationSubmitted);
});

it('nu dă tipurile de diriginte unui profesor fără dirigenție', function () {
    $profesor = derivedTeacher(UserRole::Profesor->value);

    expect($profesor->availableNotificationTypes())
        ->not->toContain(NotificationType::AbsenceMotivationSubmitted);
});
