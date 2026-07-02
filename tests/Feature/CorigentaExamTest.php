<?php

use App\Actions\GenerateCorigentaExams;
use App\Enums\CorigentaSeason;
use App\Models\CorigentaExam;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;

it('generează o intrare de corigență per disciplină restantă (medie < 5)', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create(['number' => 2]);
    $mat = Subject::factory()->create();
    $rom = Subject::factory()->create();
    $bio = Subject::factory()->create();

    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $mat->id, 'value' => 4.5]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $rom->id, 'value' => 3]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $bio->id, 'value' => 7]);

    $created = app(GenerateCorigentaExams::class)->forStudentTerm($student, $term);

    expect($created)->toBe(2)
        ->and(CorigentaExam::query()->where('student_id', $student->id)->pluck('subject_id')->all())
        ->toContain($mat->id, $rom->id)
        ->not->toContain($bio->id);
});

it('sezonul = iarnă pentru sem. I, vară pentru sem. II', function () {
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();

    $sem1 = Term::factory()->create(['number' => 1]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $sem1->id, 'subject_id' => $subject->id, 'value' => 4]);
    app(GenerateCorigentaExams::class)->forStudentTerm($student, $sem1);

    $sem2 = Term::factory()->create(['number' => 2]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $sem2->id, 'subject_id' => $subject->id, 'value' => 4]);
    app(GenerateCorigentaExams::class)->forStudentTerm($student, $sem2);

    expect(CorigentaExam::query()->where('term_id', $sem1->id)->first()->season)->toBe(CorigentaSeason::Iarna)
        ->and(CorigentaExam::query()->where('term_id', $sem2->id)->first()->season)->toBe(CorigentaSeason::Vara);
});

it('nu generează intrări pentru un elev promovat (toate mediile ≥ 5)', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create(['number' => 2]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => Subject::factory()->create()->id, 'value' => 8]);

    $created = app(GenerateCorigentaExams::class)->forStudentTerm($student, $term);

    expect($created)->toBe(0)
        ->and(CorigentaExam::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('e idempotent — rerularea nu dublează intrările', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create(['number' => 2]);
    TermAverage::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => Subject::factory()->create()->id, 'value' => 4]);

    $action = app(GenerateCorigentaExams::class);
    $action->forStudentTerm($student, $term);
    $action->forStudentTerm($student, $term);

    expect(CorigentaExam::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('generează corigență și când o componentă (sumativă) < 5, deși MS ≥ 5', function () {
    $student = Student::factory()->create();
    $term = Term::factory()->create(['number' => 2]);
    $math = Subject::factory()->create();

    TermAverage::factory()->create([
        'student_id' => $student->id,
        'term_id' => $term->id,
        'subject_id' => $math->id,
        'value' => 5,
        'mc_value' => 8,
        'summative_value' => 3,
    ]);

    $created = app(GenerateCorigentaExams::class)->forStudentTerm($student, $term);

    expect($created)->toBe(1);
});
