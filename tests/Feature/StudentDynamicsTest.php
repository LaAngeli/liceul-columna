<?php

use App\Actions\ComputeStudentDynamics;
use App\Enums\AcademicRecordPeriod;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

it('calculează evoluția generală și pe disciplină din foaia matricolă (tendință)', function () {
    $student = Student::factory()->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);

    AcademicRecord::factory()->create([
        'student_id' => $student->id, 'subject_id' => $math->id,
        'grade_level' => 7, 'period' => AcademicRecordPeriod::Annual, 'value' => 7,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id, 'subject_id' => $math->id,
        'grade_level' => 8, 'period' => AcademicRecordPeriod::Annual, 'value' => 8,
    ]);

    $dynamics = app(ComputeStudentDynamics::class)->for($student);

    expect($dynamics['general'])->toHaveCount(2)
        ->and($dynamics['general'][0])->toMatchArray(['level' => 7, 'average' => 7.0])
        ->and($dynamics['general'][1])->toMatchArray(['level' => 8, 'average' => 8.0])
        ->and($dynamics['subjects'][0]['subject'])->toBe('Matematică')
        ->and($dynamics['subjects'][0]['trend'])->toBe('up');
});

it('compară media curentă cu istoricul propriu și ridică alerta la scădere', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8]);
    $term = Term::factory()->for($year)->create(['is_current' => true, 'number' => 1]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $math = Subject::factory()->create();

    // Istoric foarte bun (anual 9 la treapta 7).
    AcademicRecord::factory()->create([
        'student_id' => $student->id, 'subject_id' => $math->id,
        'grade_level' => 7, 'period' => AcademicRecordPeriod::Annual, 'value' => 9,
    ]);

    // Prezent slab: o notă de 5 → observer-ul calculează media semestrială 5.00.
    Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $math->id,
        'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 5,
    ]);

    $dynamics = app(ComputeStudentDynamics::class)->for($student);

    expect($dynamics['current']['average'])->toBe(5.0)
        ->and($dynamics['current']['historyAverage'])->toBe(9.0)
        ->and($dynamics['current']['trend'])->toBe('down')
        ->and($dynamics['current']['alert'])->toBeTrue();
});

it('fără istoric, dinamica e goală și fără alertă', function () {
    $student = Student::factory()->create();

    $dynamics = app(ComputeStudentDynamics::class)->for($student);

    expect($dynamics['general'])->toBe([])
        ->and($dynamics['subjects'])->toBe([])
        ->and($dynamics['current']['alert'])->toBeFalse();
});
