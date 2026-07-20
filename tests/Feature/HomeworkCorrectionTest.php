<?php

/**
 * Fluxul de corecție a TEMELOR (decizia beneficiarului, 2026-07-15): profesorul-autor NU își mai
 * editează tema direct — cere corecția; Directorul / Prim-vicedirectorul / Administratorul
 * Operațional aprobă (spre deosebire de notele, unde AO doar vede arhiva). Totul rămâne în arhivă.
 */

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function hwcUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function hwcAssignment(?Teacher $teacher = null): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'subject_id' => Subject::factory(),
        'teacher_id' => $teacher?->id,
        'topic' => 'Tema veche',
        'required_task' => 'Ex. 1-3 pagina 10',
        'optional_task' => null,
    ]);
}

// ─── Cine aprobă (matricea capabilității) ────────────────────────────────────────────────

it('aprobă corecții de teme: super-admin, director, prim-vicedirector și AO; restul NU', function () {
    $allowed = [UserRole::Admin, UserRole::Director, UserRole::PrimVicedirector, UserRole::AdministratorOperational];
    $denied = [UserRole::AdministratorTehnic, UserRole::Diriginte, UserRole::Profesor, UserRole::Elev, UserRole::Parinte];

    foreach ($allowed as $role) {
        expect(hwcUser($role)->canApproveHomeworkCorrections())->toBeTrue($role->value.' ar trebui să aprobe');
    }

    foreach ($denied as $role) {
        expect(hwcUser($role)->canApproveHomeworkCorrections())->toBeFalse($role->value.' NU ar trebui să aprobe');
    }
});

it('editarea DIRECTĂ a temei e refuzată autorului-profesor și permisă aprobatorilor (policy)', function () {
    $teacherUser = hwcUser(UserRole::Profesor);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $homework = hwcAssignment($teacher);

    // Autorul NU mai poate edita direct — corectitudinea trece prin aprobare.
    expect($teacherUser->can('update', $homework))->toBeFalse()
        // Aprobatorii pot edita direct (calea excepțională).
        ->and(hwcUser(UserRole::AdministratorOperational)->can('update', $homework))->toBeTrue()
        ->and(hwcUser(UserRole::Director)->can('update', $homework))->toBeTrue()
        // Autorul își poate în continuare RETRAGE tema (soft-delete).
        ->and($teacherUser->can('delete', $homework))->toBeTrue();
});

// ─── Ciclul de viață al corecției ────────────────────────────────────────────────────────

it('aprobarea aplică pe temă DOAR câmpurile propuse și consemnează recenzentul', function () {
    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::AdministratorOperational);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_topic' => $homework->topic,
        'old_required_task' => $homework->required_task,
        'new_required_task' => 'Ex. 1-5 pagina 12',
        'reason' => 'Am greșit exercițiile.',
    ]);

    $correction->approve($reviewer->id, 'Corect.');

    $homework->refresh();
    $correction->refresh();

    expect($homework->required_task)->toBe('Ex. 1-5 pagina 12')
        // Câmpul nepropus rămâne neatins.
        ->and($homework->topic)->toBe('Tema veche')
        ->and($correction->status)->toBe(CorrectionStatus::Approved)
        ->and($correction->reviewed_by_user_id)->toBe($reviewer->id)
        ->and($correction->reviewed_at)->not->toBeNull()
        ->and($correction->review_note)->toBe('Corect.');
});

it('respingerea NU atinge tema și cere consemnarea motivului', function () {
    $homework = hwcAssignment();
    $correction = HomeworkCorrection::factory()->create([
        'homework_assignment_id' => $homework->id,
        'new_required_task' => 'Altceva',
    ]);

    $correction->reject(hwcUser(UserRole::Director)->id, 'Tema originală e corectă.');

    expect($homework->refresh()->required_task)->toBe('Ex. 1-3 pagina 10')
        ->and($correction->refresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and($correction->review_note)->toBe('Tema originală e corectă.');
});

it('o temă nu poate avea două corecții în așteptare simultan (invariant pe server)', function () {
    $homework = hwcAssignment();

    HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    expect(fn () => HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]))
        ->toThrow(ValidationException::class);
});

it('după soluționare se poate depune o NOUĂ cerere (invariantul e doar pe pending)', function () {
    $homework = hwcAssignment();

    $first = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);
    $first->reject(hwcUser(UserRole::Director)->id, 'Nu.');

    $second = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    expect($second->exists)->toBeTrue()
        ->and(HomeworkCorrection::query()->where('homework_assignment_id', $homework->id)->count())->toBe(2);
});

it('retragerea temei (soft-delete) expiră cererea în așteptare, fără recenzent', function () {
    $homework = hwcAssignment();
    $correction = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    $homework->delete();

    $correction->refresh();

    expect($correction->status)->toBe(CorrectionStatus::Expired)
        ->and($correction->reviewed_by_user_id)->toBeNull()
        // Cererea rămâne citibilă împreună cu tema retrasă (withTrashed).
        ->and($correction->homeworkAssignment)->not->toBeNull();
});

it('solicitantul își poate retrage cererea în așteptare (rămâne în arhivă)', function () {
    $correction = HomeworkCorrection::factory()->create();

    $correction->withdraw();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Withdrawn)
        ->and(HomeworkCorrection::query()->count())->toBe(1);
});

// ─── Accesul la resursă + curățarea demo (2026-07-20) ────────────────────────────────────

it('matricea de acces la arhiva corecțiilor de teme, pe toate rolurile', function (string $role, bool $needsTeacher, bool $allowed) {
    $user = hwcUser(UserRole::from($role));

    if ($needsTeacher) {
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    $response = $this->actingAs($user)->get('/admin/homework-corrections');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'super-admin' => [UserRole::Admin->value, false, true],
    'director' => [UserRole::Director->value, false, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector->value, false, true],
    'administrator operațional' => [UserRole::AdministratorOperational->value, false, true],
    // Personalul pedagogic vede (arhiva PROPRIE, prin scoping); fără fișă de profesor — nu.
    'profesor cu fișă' => [UserRole::Profesor->value, true, true],
    'diriginte cu fișă' => [UserRole::Diriginte->value, true, true],
    'profesor fără fișă' => [UserRole::Profesor->value, false, false],
    // Tehnicul n-are date academice; familia n-are panou.
    'administrator tehnic' => [UserRole::AdministratorTehnic->value, false, false],
    'elev' => [UserRole::Elev->value, false, false],
    'părinte' => [UserRole::Parinte->value, false, false],
]);

it('profesorul vede în arhivă DOAR cererile lui; administrația pe toate', function () {
    $mine = hwcUser(UserRole::Profesor);
    $mineTeacher = Teacher::factory()->create(['user_id' => $mine->id]);
    $other = hwcUser(UserRole::Profesor);
    $otherTeacher = Teacher::factory()->create(['user_id' => $other->id]);

    foreach ([[$mine, $mineTeacher], [$other, $otherTeacher]] as [$user, $teacher]) {
        HomeworkCorrection::create([
            'homework_assignment_id' => hwcAssignment($teacher)->id,
            'requested_by_user_id' => $user->id,
            'old_topic' => 'Tema veche',
            'new_topic' => 'Tema corectată',
            'reason' => 'motiv',
        ]);
    }

    $this->actingAs($mine);
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(1);

    $this->actingAs(hwcUser(UserRole::Director));
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(2);
});

it('purge-ul demo șterge corecțiile [DEMO] și temele-suport, dar nu atinge datele reale', function () {
    $teacher = Teacher::factory()->create();

    // Set DEMO: temă marcată + corecție marcată.
    $demoHomework = HomeworkAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'topic' => '[DEMO] Fracții ordinare',
        'required_task' => 'Ex. 1-4',
    ]);
    HomeworkCorrection::create([
        'homework_assignment_id' => $demoHomework->id,
        'requested_by_user_id' => hwcUser(UserRole::Profesor)->id,
        'old_required_task' => 'Ex. 1-4',
        'new_required_task' => 'Ex. 1-6',
        'reason' => '[DEMO] motiv de test',
    ]);

    // Set REAL: temă legacy + corecție autentică — trebuie să supraviețuiască.
    $realHomework = hwcAssignment($teacher);
    $realCorrection = HomeworkCorrection::create([
        'homework_assignment_id' => $realHomework->id,
        'requested_by_user_id' => hwcUser(UserRole::Profesor)->id,
        'old_topic' => 'Tema veche',
        'new_topic' => 'Tema corectată',
        'reason' => 'greșeală reală de redactare',
    ]);

    $this->artisan('app:purge-demo-data')->assertSuccessful();

    expect(HomeworkAssignment::withTrashed()->whereKey($demoHomework->id)->exists())->toBeFalse()
        ->and(HomeworkCorrection::query()->where('reason', 'like', '[DEMO]%')->exists())->toBeFalse()
        ->and(HomeworkAssignment::query()->whereKey($realHomework->id)->exists())->toBeTrue()
        ->and(HomeworkCorrection::query()->whereKey($realCorrection->id)->exists())->toBeTrue();
});
