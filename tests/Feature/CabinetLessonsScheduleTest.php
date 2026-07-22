<?php

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\WeeklySchedule;
use Spatie\Permission\Models\Role;

/**
 * Canonizare orar (O2): cabinetul primește orarul „lecții" PUBLIC al clasei elevului prin FK-ul de
 * canonizare (doar dacă e publicat), NORMALIZAT în forma `weekly` (sloturi + segmente —
 * {@see WeeklySchedule}).
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
        'rows' => [['Lecția 1 08.00 – 08.45', 'Matematică Damian Iu.']],
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$parent, $student];
}

it('cabinetul primește orarul public al clasei, normalizat pe sloturi și segmente', function () {
    $this->withoutVite();
    [$parent, $student] = childWithClassSchedule(true);

    // `weekly` e prop defer — partial reload (JSON).
    $this->actingAs($parent)
        ->get(
            route('cabinet.student', $student),
            inertiaPartialHeaders('cabinet/student-profile', 'weekly'),
        )
        ->assertOk()
        ->assertJsonPath('props.weekly.source', 'published')
        ->assertJsonPath('props.weekly.label', 'Clasa IX 2')
        ->assertJsonPath('props.weekly.days.0.value', 1)
        ->assertJsonPath('props.weekly.slots.0.number', 1)
        ->assertJsonPath('props.weekly.slots.0.time', '08.00–08.45')
        ->assertJsonPath('props.weekly.slots.0.kind', 'lesson')
        // Celula „Matematică Damian Iu." e SEGMENTATĂ: disciplină + profesor separate.
        ->assertJsonPath('props.weekly.slots.0.cells.1.segments.0.subject', 'Matematică')
        ->assertJsonPath('props.weekly.slots.0.cells.1.segments.0.teacher', 'Damian Iu.');
});

it('orarul nepublicat (draft) nu ajunge în cabinet', function () {
    $this->withoutVite();
    [$parent, $student] = childWithClassSchedule(false);

    $this->actingAs($parent)
        ->get(
            route('cabinet.student', $student),
            inertiaPartialHeaders('cabinet/student-profile', 'weekly'),
        )
        ->assertOk()
        // Fără orar publicat și fără orar structurat → nimic (EmptyState în UI).
        ->assertJsonPath('props.weekly', null);
});
