<?php

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Canonizare orar (O2): cabinetul primește orarul „lecții" PUBLIC al clasei elevului prin FK-ul de
 * canonizare (doar dacă e publicat). Conectează cabinetul la orarul public bogat.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function childWithClassSchedule(bool $public): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['name' => 'IX', 'section' => '2', 'grade_level' => 9]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa IX 2',
        'school_class_id' => $class->id,
        'is_public' => $public,
        'headers' => ['', 'Luni'],
        'rows' => [['Lecția 1', 'Matematică']],
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$parent, $student];
}

it('cabinetul primește orarul public al clasei când e legat și publicat', function () {
    $this->withoutVite();
    [$parent, $student] = childWithClassSchedule(true);

    $this->actingAs($parent)
        ->get(route('cabinet.student', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lessonsSchedule.label', 'Clasa IX 2')
            ->where('lessonsSchedule.headers.1', 'Luni')
            ->where('lessonsSchedule.rows.0.1', 'Matematică'));
});

it('orarul nepublicat (draft) nu ajunge în cabinet', function () {
    $this->withoutVite();
    [$parent, $student] = childWithClassSchedule(false);

    $this->actingAs($parent)
        ->get(route('cabinet.student', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('lessonsSchedule', null));
});
