<?php

/**
 * Secțiunea „Discipline" pe rol (2026-07-15, feedback beneficiar): profesorul își vede DOAR
 * disciplinele lui; dirigintele vede și disciplinele predate în clasa lui (cu profesorii lor);
 * administrația vede tot nomenclatorul. Treptele se afișează cu cifre romane (clase, nu note).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);

    $this->mySubject = Subject::factory()->create(['name' => 'SUBJ-Mea', 'min_grade' => 5, 'max_grade' => 12]);
    $this->classSubject = Subject::factory()->create(['name' => 'SUBJ-Clasa', 'min_grade' => 5, 'max_grade' => 9]);
    $this->unrelatedSubject = Subject::factory()->create(['name' => 'SUBJ-Straina', 'min_grade' => 1, 'max_grade' => 4]);

    // Profesorul-diriginte: predă SUBJ-Mea în 7 A (clasa lui de diriginție).
    $this->user = User::factory()->create();
    $this->user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->mySubject->id,
    ]);

    // Colegul predă SUBJ-Clasa în aceeași clasă.
    $colleagueUser = User::factory()->create();
    $colleagueUser->assignRole(UserRole::Profesor->value);
    $this->colleague = Teacher::factory()->create(['user_id' => $colleagueUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->colleague->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->classSubject->id,
    ]);
});

it('profesorul (ne-diriginte) vede DOAR disciplinele pe care le predă', function () {
    actingAs($this->user);

    Livewire::test(ListSubjects::class)
        ->assertCanSeeTableRecords([$this->mySubject])
        ->assertCanNotSeeTableRecords([$this->classSubject, $this->unrelatedSubject]);
});

it('dirigintele vede și disciplinele predate în clasa lui, nu și restul nomenclatorului', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    actingAs($this->user);

    Livewire::test(ListSubjects::class)
        ->assertCanSeeTableRecords([$this->mySubject, $this->classSubject])
        ->assertCanNotSeeTableRecords([$this->unrelatedSubject]);
});

it('administrația vede tot nomenclatorul', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    Livewire::test(ListSubjects::class)
        ->assertCanSeeTableRecords([$this->mySubject, $this->classSubject, $this->unrelatedSubject]);
});

it('treptele se afișează cu cifre ROMANE (clase, nu scară de notare)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    Livewire::test(ListSubjects::class)
        ->assertSee('V–XII')
        ->assertSee('I–IV');
});

it('dirigintele vede cine predă disciplina colegului în clasa lui', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    actingAs($this->user);

    Livewire::test(ListSubjects::class)
        ->assertSee($this->colleague->full_name);
});

it('scoping-ul se aplică și pe interogarea resursei (nu doar pe tabel)', function () {
    actingAs($this->user);

    expect(SubjectResource::getEloquentQuery()->pluck('id')->all())->toBe([$this->mySubject->id]);
});
