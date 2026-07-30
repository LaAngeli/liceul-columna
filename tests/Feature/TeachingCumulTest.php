<?php

/**
 * CUMUL-ul pedagogic: diriginții care predau primesc și rolul Profesor („varianta A", decizia
 * beneficiarului 2026-07-31, luată pe analiza de impact măsurată pe date reale).
 *
 * Contractul:
 *   - DREPTURILE nu se schimbă — aceleași clase, doar separate pe contexte;
 *   - ATERIZAREA implicită devine „Profesor" (munca de zi cu zi), altfel un cadru care predă la 14
 *     clase ar deschide panoul în contextul Diriginte și ar vedea o singură clasă;
 *   - membria Profesor se ADAUGĂ la primirea alocărilor și NU se retrage automat (statut de bază);
 *   - conducerea și familia nu intră niciodată sub regulă.
 */

use App\Actions\SyncHomeroomRole;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActiveRole;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->subject = Subject::factory()->create();
});

/** Cont mono-rol „diriginte" ca cele importate din legacy (func=3), care PREDĂ. */
function legacyHomeroomTeacher(AcademicYear $year, Subject $subject, int $taughtClasses = 3): User
{
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    // Dirigenția, scrisă prin QUERY BUILDER: pe calea de model observerul i-ar adăuga rolul
    // Diriginte, iar noi reproducem starea MOȘTENITĂ (rol unic, venit din import).
    $homeroom = SchoolClass::factory()->for($year)->create();
    SchoolClass::query()->whereKey($homeroom->id)->update(['homeroom_teacher_id' => $teacher->id]);

    foreach (range(1, $taughtClasses) as $i) {
        $class = SchoolClass::factory()->for($year)->create();
        // Idem: alocările prin query builder nu declanșează membria Profesor.
        TeachingAssignment::query()->insert([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $user->syncRoles([UserRole::Diriginte->value]);

    return $user->fresh();
}

it('acordă rolul Profesor diriginților care predau, cu aterizare implicită pe Profesor', function () {
    $user = legacyHomeroomTeacher($this->year, $this->subject);

    expect($user->getRoleNames()->all())->toBe([UserRole::Diriginte->value])
        ->and($user->isMultiRole())->toBeFalse();

    $this->artisan('app:grant-teaching-cumul', ['--apply' => true])->assertSuccessful();

    $user = $user->fresh();

    expect($user->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Diriginte->value, UserRole::Profesor->value])
        ->and($user->isMultiRole())->toBeTrue()
        // Aterizarea implicită: contextul de zi cu zi, nu cel de prioritate.
        ->and($user->preferred_role)->toBe(UserRole::Profesor->value)
        ->and($user->activeRole())->toBe(UserRole::Profesor);
});

it('drepturile NU se schimbă — aceleași clase, doar separate pe contexte', function () {
    $user = legacyHomeroomTeacher($this->year, $this->subject, taughtClasses: 3);

    // Înainte: perimetru FUZIONAT (3 predate + 1 dirigenție).
    $inainte = $user->contextClassIds();
    expect($inainte)->toHaveCount(4)
        ->and($user->teachingContext())->toBeNull();

    $this->artisan('app:grant-teaching-cumul', ['--apply' => true])->assertSuccessful();
    $user = $user->fresh();
    $this->actingAs($user);

    // După: aceleași 4 clase, dar despărțite — reuniunea contextelor = perimetrul de dinainte.
    $caProfesor = $user->contextClassIds();

    $user->forceFill(['preferred_role' => UserRole::Diriginte->value])->save();
    $caDiriginte = $user->fresh()->contextClassIds();

    expect($caProfesor)->toHaveCount(3)
        ->and($caDiriginte)->toHaveCount(1)
        ->and(array_values(array_unique([...$caProfesor, ...$caDiriginte])))
        ->toEqualCanonicalizing($inainte);
});

it('dry-run raportează fără să scrie nimic', function () {
    $user = legacyHomeroomTeacher($this->year, $this->subject);

    $this->artisan('app:grant-teaching-cumul')->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Diriginte->value])
        ->and($user->fresh()->preferred_role)->toBeNull();
});

it('e idempotentă — a doua rulare nu mai are ce acorda', function () {
    legacyHomeroomTeacher($this->year, $this->subject);

    $this->artisan('app:grant-teaching-cumul', ['--apply' => true])->assertSuccessful();

    $this->artisan('app:grant-teaching-cumul')
        ->expectsOutputToContain('Nimic de acordat')
        ->assertSuccessful();
});

it('NU atinge dirigintele care nu predă nimic', function () {
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class = SchoolClass::factory()->for($this->year)->create();
    SchoolClass::query()->whereKey($class->id)->update(['homeroom_teacher_id' => $teacher->id]);
    $user->syncRoles([UserRole::Diriginte->value]);

    $this->artisan('app:grant-teaching-cumul')
        ->expectsOutputToContain('Nimic de acordat')
        ->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Diriginte->value]);
});

it('NU atinge conducerea care predă — cumul-ul e strict pentru corpul didactic', function () {
    $director = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $director->id]);
    $class = SchoolClass::factory()->for($this->year)->create();
    TeachingAssignment::query()->insert([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => $this->subject->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $director->syncRoles([UserRole::Director->value]);

    $this->artisan('app:grant-teaching-cumul')
        ->expectsOutputToContain('Nimic de acordat')
        ->assertSuccessful();

    expect($director->fresh()->getRoleNames()->all())->toBe([UserRole::Director->value]);
});

// ── Membria continuă (observerul pe alocări) ───────────────────────────────────────────────────

it('primirea unei alocări ADAUGĂ rolul Profesor — cumul-ul nu rămâne o reparație unică', function () {
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class = SchoolClass::factory()->for($this->year)->create();
    SchoolClass::query()->whereKey($class->id)->update(['homeroom_teacher_id' => $teacher->id]);
    $user->syncRoles([UserRole::Diriginte->value]);

    // Prin MODEL (calea aplicației) → observerul acordă membria pe loc.
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => $this->subject->id,
    ]);

    expect($user->fresh()->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Diriginte->value, UserRole::Profesor->value]);
});

it('pierderea alocărilor NU retrage rolul Profesor — e statutul de bază al corpului didactic', function () {
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $user->syncRoles([UserRole::Profesor->value]);

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => SchoolClass::factory()->for($this->year)->create()->id,
        'subject_id' => $this->subject->id,
    ]);

    $assignment->delete();

    // Spre deosebire de dirigenție (funcție atribuită pe o clasă), „profesor" rămâne.
    expect($user->fresh()->getRoleNames()->all())->toBe([UserRole::Profesor->value]);
});

it('învățătorul de primar care predă DOAR la clasa lui vede aceeași clasă, dar cu drepturi diferite', function () {
    // Cazul măsurat pe date reale: 11 din cele 20 de conturi au predă=1, dirigenție=1 — aceeași
    // clasă. Comutatorul nu separă VIZIBILITATEA, dar separă DREPTURILE (motivările).
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class = SchoolClass::factory()->for($this->year)->create();
    SchoolClass::query()->whereKey($class->id)->update(['homeroom_teacher_id' => $teacher->id]);
    TeachingAssignment::query()->insert([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => $this->subject->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->syncRoles([UserRole::Diriginte->value]);

    $this->artisan('app:grant-teaching-cumul', ['--apply' => true])->assertSuccessful();
    $user = $user->fresh();
    $this->actingAs($user);

    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);
    expect($user->contextClassIds())->toBe([$class->id])
        ->and($user->canMotivateAbsencesFor($class->id))->toBeFalse();

    session()->put(ActiveRole::SESSION_KEY, UserRole::Diriginte->value);
    expect($user->contextClassIds())->toBe([$class->id])
        ->and($user->canMotivateAbsencesFor($class->id))->toBeTrue();
});

it('acordarea directă e refuzată pentru cine nu predă', function () {
    $user = User::factory()->create();
    Teacher::factory()->create(['user_id' => $user->id]);
    $user->syncRoles([UserRole::Diriginte->value]);

    expect(app(SyncHomeroomRole::class)->grantTeacherMembership($user->fresh()))->toBeNull();
});
