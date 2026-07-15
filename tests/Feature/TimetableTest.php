<?php

use App\Actions\ComputeDeferralRisk;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('resursa „Orar structurat" e gestionată de administratorul operațional, nu de profesor', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $this->actingAs($ao)->get('/admin/lessons')->assertOk();
    $this->actingAs($profesor)->get('/admin/lessons')->assertForbidden();
});

it('cabinetul afișează orarul structurat al clasei elevului', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $subject = Subject::factory()->create(['name' => 'Matematică']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    Lesson::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // `timetable` e prop defer — partial reload (JSON).
    $this->actingAs($parent)
        ->get(
            "/cabinet/elev/{$student->id}",
            inertiaPartialHeaders('cabinet/student-profile', 'timetable'),
        )
        ->assertOk()
        ->assertJsonStructure(['props' => ['timetable' => ['days', 'grid']]]);
});

it('riscul de amânare apare la disciplina cu ≤1 notă și >50% absențe din lecțiile programate', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    // Interval FIX luni→duminică, exact 10 săptămâni (50 de zile lucrătoare) — cu `now()`,
    // numărul de săptămâni (ceil pe zile lucrătoare/5) varia cu ziua rulării și testul
    // devenea fragil la dată (pragul 11 > 50%·22 pica).
    $term = Term::factory()->for($year)->create([
        'is_current' => true,
        'starts_on' => '2026-09-07',
        'ends_on' => '2026-11-15',
    ]);
    $subject = Subject::factory()->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    // 2 lecții/săptămână → 2 × 10 = 20 lecții programate.
    foreach ([Weekday::Monday, Weekday::Tuesday] as $day) {
        Lesson::factory()->create([
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'day_of_week' => $day,
            'lesson_number' => 1,
        ]);
    }

    // 11 absențe (> 50% din 20), 0 note → risc.
    Absence::factory()->count(11)->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
        'is_motivated' => false,
    ]);

    $risks = app(ComputeDeferralRisk::class)->for($student);

    expect($risks)->toHaveCount(1)
        ->and($risks[0]['scheduled'])->toBe(20)
        ->and($risks[0]['absences'])->toBe(11);
});
