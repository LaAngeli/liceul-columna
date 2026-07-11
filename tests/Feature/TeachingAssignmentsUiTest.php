<?php

/**
 * Interfața pentru ALOCĂRI (task #30): până acum nu exista NICIO cale în panou de a aloca un
 * profesor la (clasă, disciplină) — o disciplină nouă era o fundătură (nimeni nu putea preda/nota
 * la ea fără tinker/import). RelationManager pe pagina profesorului, scriabil doar de configuratori
 * (TeachingAssignmentPolicy); alocarea alimentează direct scoping-ul catalogului.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\RelationManagers\TeachingAssignmentsRelationManager;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->subject = Subject::factory()->create();
    $this->teacher = Teacher::factory()->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

/** @return Testable */
function assignmentsManager($test)
{
    return Livewire::test(TeachingAssignmentsRelationManager::class, [
        'ownerRecord' => $test->teacher,
        'pageClass' => EditTeacher::class,
    ]);
}

it('configuratorul creează o alocare, iar scoping-ul catalogului o preia imediat', function () {
    actingAs($this->director);

    expect($this->teacher->canGradeClassSubject($this->class->id, $this->subject->id))->toBeFalse();

    assignmentsManager($this)
        ->callTableAction('create', data: [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
        ])
        ->assertHasNoTableActionErrors();

    expect(TeachingAssignment::query()
        ->where('teacher_id', $this->teacher->id)
        ->where('school_class_id', $this->class->id)
        ->where('subject_id', $this->subject->id)
        ->exists())->toBeTrue()
        // Alocarea = fundamentul scoping-ului: profesorul poate nota ACUM la (clasa, disciplina).
        ->and($this->teacher->fresh()->canGradeClassSubject($this->class->id, $this->subject->id))->toBeTrue();
});

it('duplicatul e respins cu mesaj clar; cel ARHIVAT îndrumă spre restaurare (nu eroare SQL)', function () {
    actingAs($this->director);

    $existing = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'english_group' => null,
    ]);

    assignmentsManager($this)
        ->callTableAction('create', data: [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
        ])
        ->assertHasTableActionErrors(['subject_id']);

    // Arhivată → indexul unic încă o vede → mesaj de restaurare, nu recreare.
    $existing->delete();

    assignmentsManager($this)
        ->callTableAction('create', data: [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
        ])
        ->assertHasTableActionErrors(['subject_id']);

    // Aceeași pereche cu GRUPĂ diferită NU e duplicat (engleza pe grupe).
    assignmentsManager($this)
        ->callTableAction('create', data: [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'english_group' => 1,
        ])
        ->assertHasNoTableActionErrors();
});

it('retragerea alocării e soft-delete: scoping-ul dispare, dar rândul rămâne restaurabil', function () {
    actingAs($this->director);

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    assignmentsManager($this)
        ->callTableAction('delete', $assignment)
        ->assertHasNoTableActionErrors();

    expect(TeachingAssignment::withTrashed()->find($assignment->id)->trashed())->toBeTrue()
        ->and($this->teacher->fresh()->canGradeClassSubject($this->class->id, $this->subject->id))->toBeFalse();
});

it('doar configuratorii scriu alocări; profesorul nu (policy pe server, nu doar UI)', function () {
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $profesor->id]);

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    expect(Gate::forUser($this->director)->check('create', TeachingAssignment::class))->toBeTrue()
        ->and(Gate::forUser($profesor)->check('create', TeachingAssignment::class))->toBeFalse()
        ->and(Gate::forUser($profesor)->check('delete', $assignment))->toBeFalse();
});
