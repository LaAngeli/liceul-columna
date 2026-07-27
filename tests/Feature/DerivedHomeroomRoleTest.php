<?php

/**
 * ROLUL „DIRIGINTE" E DERIVAT DIN DESEMNARE (decizie 2026-07-27).
 *
 * Rolul nu dădea niciun drept — toate drepturile de dirigenție vin din `homeroom_teacher_id`.
 * Rămăsese o copie manuală a unei realități care se schimbă fără el, deci eticheta putea minți.
 * Acum e o consecință: cine primește o clasă devine „Diriginte", cine o pierde revine „Profesor".
 *
 * Plus reparația care ținea de aceeași cauză: matricea de notificări se uita la ROL, deci un
 * „Profesor" desemnat diriginte judeca cererile clasei lui fără să poată configura notificarea.
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

it('promovează la „Diriginte" pe cel care primește o clasă', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $class = SchoolClass::factory()->for($this->year)->create();

    expect($user->getRoleNames()->first())->toBe(UserRole::Profesor->value);

    $class->update(['homeroom_teacher_id' => $user->teacher->id]);

    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);
});

it('readuce la „Profesor" pe cel care pierde ultima clasă', function () {
    $user = derivedTeacher(UserRole::Diriginte->value);
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    $class->update(['homeroom_teacher_id' => null]);

    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value);
});

it('păstrează „Diriginte" cât timp mai rămâne măcar o clasă', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $first = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);
    SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    $first->update(['homeroom_teacher_id' => null]);

    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);
});

it('mută eticheta la AMBELE capete când dirigenția e reatribuită', function () {
    $cedent = derivedTeacher(UserRole::Diriginte->value);
    $primitor = derivedTeacher(UserRole::Profesor->value);

    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $cedent->teacher->id]);

    $class->update(['homeroom_teacher_id' => $primitor->teacher->id]);

    expect($cedent->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value)
        ->and($primitor->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);
});

it('ștergerea clasei retrage eticheta, restaurarea o readuce', function () {
    $user = derivedTeacher(UserRole::Profesor->value);
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $user->teacher->id]);

    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);

    $class->delete();
    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value);

    $class->restore();
    expect($user->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);
});

it('NU retrogradează conducerea care primește o clasă — dirigenția e o funcție în plus', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $teacher = Teacher::factory()->create(['user_id' => $director->id]);

    SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $teacher->id]);

    expect($director->fresh()->getRoleNames()->first())->toBe(UserRole::Director->value);
});

it('sincronizează starea moștenită, scrisă pe lângă observeri', function () {
    $orfan = derivedTeacher(UserRole::Diriginte->value);
    $ascuns = derivedTeacher(UserRole::Profesor->value);

    // Scriere prin QUERY BUILDER: exact cum o fac importurile — observerul nu se declanșează.
    $class = SchoolClass::factory()->for($this->year)->create();
    SchoolClass::query()->whereKey($class->id)->update(['homeroom_teacher_id' => $ascuns->teacher->id]);

    expect($ascuns->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value);

    $sync = app(SyncHomeroomRole::class);

    expect($sync->drifted())->toHaveCount(2);

    foreach ($sync->drifted() as $row) {
        $sync->forUser($row['user']);
    }

    expect($orfan->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value)
        ->and($ascuns->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value)
        // Idempotentă: a doua trecere nu mai are ce raporta.
        ->and(app(SyncHomeroomRole::class)->drifted())->toBe([]);
});

it('comanda raportează fără să scrie, iar cu --apply scrie', function () {
    $orfan = derivedTeacher(UserRole::Diriginte->value);

    $this->artisan('app:sync-homeroom-roles')->assertSuccessful();
    expect($orfan->fresh()->getRoleNames()->first())->toBe(UserRole::Diriginte->value);

    $this->artisan('app:sync-homeroom-roles', ['--apply' => true])->assertSuccessful();
    expect($orfan->fresh()->getRoleNames()->first())->toBe(UserRole::Profesor->value);

    $this->artisan('app:sync-homeroom-roles')
        ->expectsOutputToContain('Nimic de sincronizat')
        ->assertSuccessful();
});

// ── Matricea de notificări urmează desemnarea ──────────────────────────────────────────────────

it('dă tipurile de diriginte celui DESEMNAT, chiar dacă rolul contului nu e „Diriginte"', function () {
    // Conducerea care primește o clasă îți păstrează rolul (vezi mai sus) — deci aici rolul
    // CHIAR rămâne diferit de funcție, iar notificările trebuie să urmeze funcția.
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
