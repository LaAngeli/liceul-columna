<?php

use App\Actions\DetermineStudentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;

function statusFor(Student $student, Term $term): array
{
    return app(DetermineStudentStatus::class)->forTerm($student->id, $term->id);
}

it('corigent dacă cel puțin o medie < 5, cu disciplinele restante', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create();
    $rom = Subject::factory()->create(['name' => 'Limba română']);
    $math = Subject::factory()->create(['name' => 'Matematică']);

    TermAverage::factory()->create(['student_id' => $student->id, 'subject_id' => $rom->id, 'term_id' => $term->id, 'value' => 8]);
    TermAverage::factory()->create(['student_id' => $student->id, 'subject_id' => $math->id, 'term_id' => $term->id, 'value' => 3]);

    $result = statusFor($student, $term);

    expect($result['status'])->toBe(StudentStatus::Corigent)
        ->and($result['failingSubjects'])->toBe(['Matematică']);
});

it('promovat dacă toate mediile ≥ 5', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create();

    TermAverage::factory()->create(['student_id' => $student->id, 'subject_id' => Subject::factory()->create()->id, 'term_id' => $term->id, 'value' => 8]);
    TermAverage::factory()->create(['student_id' => $student->id, 'subject_id' => Subject::factory()->create()->id, 'term_id' => $term->id, 'value' => 5]);

    $result = statusFor($student, $term);

    expect($result['status'])->toBe(StudentStatus::Promovat)
        ->and($result['failingSubjects'])->toBe([]);
});

it('nedeterminabil (null) când nu există medii', function () {
    $result = statusFor(Student::factory()->create(), Term::factory()->create());

    expect($result['status'])->toBeNull()
        ->and($result['average'])->toBeNull();
});
