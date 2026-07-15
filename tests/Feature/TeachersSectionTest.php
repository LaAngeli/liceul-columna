<?php

/**
 * Secțiunea „Profesori" pe rol (2026-07-15): profesorul vede echipa claselor lui (fără date
 * personale — email/cont doar administrației); dirigintele vede și ce predă fiecare în clasa
 * lui; administrația vede registrul complet. Editarea rămâne a configuratorilor.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Teachers\TeacherResource;
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

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->otherClass = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9, 'section' => 'B']);
    $this->subject = Subject::factory()->create();
    $this->otherSubject = Subject::factory()->create();

    // Viewer-ul: predă în 7 A.
    $this->user = User::factory()->create();
    $this->user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id,
    ]);

    // Colegul din ACEEAȘI clasă (7 A) — cu email pe fișă (nu trebuie văzut de coleg).
    $this->colleague = Teacher::factory()->create(['email' => 'coleg.secret@columna.test']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->colleague->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->otherSubject->id,
    ]);

    // Profesor STRĂIN (predă doar în 9 B) — invizibil viewer-ului.
    $this->stranger = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->stranger->id, 'school_class_id' => $this->otherClass->id, 'subject_id' => $this->subject->id,
    ]);
});

it('profesorul vede echipa claselor lui (el + colegii clasei), nu profesorii străini', function () {
    actingAs($this->user);

    Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher, $this->colleague])
        ->assertCanNotSeeTableRecords([$this->stranger]);
});

it('profesorul NU vede email-ul colegului (coloană rezervată administrației)', function () {
    actingAs($this->user);

    Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->colleague])
        ->assertDontSee('coleg.secret@columna.test');
});

it('dirigintele vede dirigintele clasei lui și disciplinele colegilor în clasa lui', function () {
    $this->class->update(['homeroom_teacher_id' => $this->colleague->id]);

    actingAs($this->user);

    $component = Livewire::test(ListTeachers::class);

    // Harta diriginte→clasă + disciplinele colegului în clasele viewer-ului.
    expect($component->instance()->homeroomOfMap()->get($this->colleague->id))->toBe('VII A')
        ->and($component->instance()->teachesInMyClassesMap()->get($this->colleague->id))
        ->toContain($this->otherSubject->name);
});

it('administrația vede tot registrul, inclusiv email-ul', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher, $this->colleague, $this->stranger])
        ->assertSee('coleg.secret@columna.test');
});

it('profesorul nu vede acțiunea de editare pe fișa colegului; configuratorul o vede', function () {
    actingAs($this->user);
    Livewire::test(ListTeachers::class)
        ->assertTableActionHidden('edit', $this->colleague);

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);
    Livewire::test(ListTeachers::class)
        ->assertTableActionVisible('edit', $this->colleague);
});

it('scoping-ul se aplică pe interogarea resursei (nu doar pe tabel)', function () {
    actingAs($this->user);

    expect(TeacherResource::getEloquentQuery()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->teacher->id, $this->colleague->id])->sort()->values()->all());
});
