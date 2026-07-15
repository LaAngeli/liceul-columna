<?php

/**
 * Secțiunea „Profesori" = registrul ADMINISTRAȚIEI (decizia beneficiarului, 2026-07-15:
 * nu se deschide cadrelor didactice). Adminul vede fișele + „Diriginte al" + „Acoperire";
 * profesorul primește 403.
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
use function Pest\Laravel\get;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->subject = Subject::factory()->create();

    $this->teacher = Teacher::factory()->create(['email' => 'profesor@columna.internal']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id,
    ]);
});

it('profesorul NU are acces la secțiunea Profesori (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(TeacherResource::getUrl('index'))->assertForbidden();
});

it('administrația vede registrul: email, diriginte al, acoperire', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher])
        ->assertSee('profesor@columna.internal');

    expect($component->instance()->homeroomOfMap()->get($this->teacher->id))->toBe('VII A');
});

it('prim-vicedirectorul vede registrul, dar nu poate șterge fișe (policy de configurator)', function () {
    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher]);

    expect($pvd->can('delete', $this->teacher))->toBeFalse();
});
