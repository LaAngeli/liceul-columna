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

it('decizia e pe MS: componentă sub 5 dar MS ≥ 5 → promovat (fără prag pe componente)', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);

    // MC = 7, sumativă = 3 → MS = 5,00 ≥ 5 → promovat. Notele se mediază, componentele n-au prag.
    TermAverage::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $math->id,
        'term_id' => $term->id,
        'value' => 5,
        'mc_value' => 7,
        'summative_value' => 3,
    ]);

    expect(statusFor($student, $term)['status'])->toBe(StudentStatus::Promovat);
});

it('corigent când MS a disciplinei < 5, chiar dacă sumativa e ≥ 5', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);

    // MC = 3, sumativă = 6 → MS = 4,50 < 5 → corigent (sumativa bună nu salvează media mică).
    TermAverage::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $math->id,
        'term_id' => $term->id,
        'value' => 4.5,
        'mc_value' => 3,
        'summative_value' => 6,
    ]);

    $result = statusFor($student, $term);

    expect($result['status'])->toBe(StudentStatus::Corigent)
        ->and($result['failingSubjects'])->toBe(['Matematică']);
});
