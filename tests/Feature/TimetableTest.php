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

/*
 * REGULĂ RESCRISĂ DELIBERAT (LOT 5): profesorul VEDE acum orarul (§3.3 îi dă dreptul, scoped pe
 * clasele lui), dar tot nu-l GESTIONEAZĂ. Înainte, citirea și scrierea treceau prin aceeași
 * capabilitate, deci accesul îi era refuzat cu 403 — testul verifica exact conflatarea reparată.
 */
it('resursa „Orar structurat": profesorul o VEDE, dar gestionarea rămâne a administratorului operațional', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    $tehnic = User::factory()->create();
    $tehnic->assignRole(UserRole::AdministratorTehnic->value);

    $this->actingAs($ao)->get('/admin/lessons')->assertOk();
    $this->actingAs($profesor)->get('/admin/lessons')->assertOk();
    // Infrastructura rămâne în afara datelor academice (§3.2).
    $this->actingAs($tehnic)->get('/admin/lessons')->assertForbidden();

    // Vederea nu aduce scrierea: pagina de creare rămâne închisă profesorului.
    $this->actingAs($profesor)->get('/admin/lessons/create')->assertForbidden();
    $this->actingAs($ao)->get('/admin/lessons/create')->assertOk();
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

    // Forma s-a schimbat deliberat: acțiunea întoarce ACUM două liste, fiindcă „nu e risc" și „nu
    // pot calcula" nu sunt același lucru (vezi DeferralRiskCoverageTest).
    $result = app(ComputeDeferralRisk::class)->for($student);

    expect($result['risks'])->toHaveCount(1)
        ->and($result['risks'][0]['scheduled'])->toBe(20)
        ->and($result['risks'][0]['absences'])->toBe(11)
        // Disciplina e în orar, deci evaluabilă — nu apare ca nedeterminată.
        ->and($result['undetermined'])->toBe([]);
});
