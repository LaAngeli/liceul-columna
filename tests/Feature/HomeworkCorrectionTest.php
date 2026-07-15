<?php

/**
 * Fluxul de corecție a TEMELOR (decizia beneficiarului, 2026-07-15): profesorul-autor NU își mai
 * editează tema direct — cere corecția; Directorul / Prim-vicedirectorul / Administratorul
 * Operațional aprobă (spre deosebire de notele, unde AO doar vede arhiva). Totul rămâne în arhivă.
 */

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
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
