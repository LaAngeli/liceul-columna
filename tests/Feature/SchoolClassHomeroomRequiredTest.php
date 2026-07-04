<?php

use App\Enums\UserRole;
use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Invariant: o clasă cu elevi NU trebuie să rămână fără diriginte. Îl impunem pe calea normală
 * (formularul de creare) — DB rămâne intenționat nullable pentru import/vacanță, rezolvate în
 * widget-ul ClassesNeedingHomeroom. Vezi SchoolClassForm.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value); // canConfigureSchool → poate crea clase
    $this->actingAs($director);
});

it('respinge crearea unei clase fără diriginte (câmp obligatoriu)', function () {
    $year = AcademicYear::factory()->create();

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $year->id,
            'grade_level' => 8,
            'name' => 'VIII',
            'section' => 'A',
            'homeroom_teacher_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['homeroom_teacher_id' => 'required']);

    expect(SchoolClass::query()->count())->toBe(0);
});

it('permite crearea când dirigintele e specificat', function () {
    $year = AcademicYear::factory()->create();
    $teacher = Teacher::factory()->create();

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $year->id,
            'grade_level' => 8,
            'name' => 'VIII',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SchoolClass::query()->where('homeroom_teacher_id', $teacher->id)->exists())->toBeTrue();
});
