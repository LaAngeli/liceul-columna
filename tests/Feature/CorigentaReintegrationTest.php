<?php

use App\Actions\DetermineStudentStatus;
use App\Enums\AcademicRecordPeriod;
use App\Enums\CorigentaSeason;
use App\Enums\StudentStatus;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;

/**
 * @return array{class: SchoolClass, term: Term, student: Student}
 */
function corigentaCtx(int $gradeLevel = 8): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => $gradeLevel]);
    $student = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'academic_year_id' => $year->id,
    ]);

    return [
        'class' => $class,
        'term' => Term::factory()->for($year)->create(),
        'student' => $student,
    ];
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student}  $ctx
 */
function failingAverage(array $ctx, Subject $subject): void
{
    TermAverage::factory()->create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $subject->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => 4,
    ]);
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student}  $ctx
 */
function corigentaFor(array $ctx, Subject $subject, ?float $mark): CorigentaExam
{
    return CorigentaExam::create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $subject->id,
        'term_id' => $ctx['term']->id,
        'season' => CorigentaSeason::Iarna,
        'mark' => $mark,
    ]);
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student}  $ctx
 * @return array{status: StudentStatus|null, failingSubjects: array<int, string>, average: float|null}
 */
function statusForCtx(array $ctx): array
{
    return app(DetermineStudentStatus::class)->forTerm($ctx['student']->id, $ctx['term']->id);
}

it('passed e derivat din notă: null fără notă, ≥5 → true, <5 → false', function () {
    $ctx = corigentaCtx();

    expect(corigentaFor($ctx, Subject::factory()->create(), null)->isPassed())->toBeNull()
        ->and(corigentaFor($ctx, Subject::factory()->create(), 6)->isPassed())->toBeTrue()
        ->and(corigentaFor($ctx, Subject::factory()->create(), 4)->isPassed())->toBeFalse();
});

it('corigent cât timp corigența nu e dată', function () {
    $ctx = corigentaCtx();
    failingAverage($ctx, Subject::factory()->create());

    expect(statusForCtx($ctx)['status'])->toBe(StudentStatus::Corigent);
});

it('promovat după ce corigența e trecută (notă ≥ 5)', function () {
    $ctx = corigentaCtx();
    $subject = Subject::factory()->create();
    failingAverage($ctx, $subject);
    corigentaFor($ctx, $subject, 6);

    $result = statusForCtx($ctx);

    expect($result['status'])->toBe(StudentStatus::Promovat)
        ->and($result['failingSubjects'])->toBe([]);
});

it('repetent după ce corigența e picată', function () {
    $ctx = corigentaCtx();
    $subject = Subject::factory()->create();
    failingAverage($ctx, $subject);
    corigentaFor($ctx, $subject, 4);

    expect(statusForCtx($ctx)['status'])->toBe(StudentStatus::Repetent);
});

it('rămâne corigent dacă o corigență e trecută dar alta încă nedată', function () {
    $ctx = corigentaCtx();
    $mat = Subject::factory()->create();
    $rom = Subject::factory()->create();
    failingAverage($ctx, $mat);
    failingAverage($ctx, $rom);
    corigentaFor($ctx, $mat, 7); // trecută; rom rămâne nedată (pending)

    expect(statusForCtx($ctx)['status'])->toBe(StudentStatus::Corigent);
});

it('repetent dacă toate corigențele sunt date și cel puțin una picată', function () {
    $ctx = corigentaCtx();
    $mat = Subject::factory()->create();
    $rom = Subject::factory()->create();
    failingAverage($ctx, $mat);
    failingAverage($ctx, $rom);
    corigentaFor($ctx, $mat, 7); // trecută
    corigentaFor($ctx, $rom, 3); // picată

    expect(statusForCtx($ctx)['status'])->toBe(StudentStatus::Repetent);
});

it('nota de corigență intră în foaia matricolă (media anuală a treptei)', function () {
    $ctx = corigentaCtx(8);
    $subject = Subject::factory()->create();
    corigentaFor($ctx, $subject, 6);

    $record = AcademicRecord::query()
        ->where('student_id', $ctx['student']->id)
        ->where('subject_id', $subject->id)
        ->where('grade_level', 8)
        ->where('period', AcademicRecordPeriod::Annual)
        ->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->value)->toBe(6.0);
});
