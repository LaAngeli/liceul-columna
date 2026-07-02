<?php

use App\Enums\EvaluationType;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;

/**
 * @return array{class: SchoolClass, term: Term, student: Student, subject: Subject}
 */
function avgSetup(int $gradeLevel): array
{
    $year = AcademicYear::factory()->create();

    return [
        'class' => SchoolClass::factory()->for($year)->create(['grade_level' => $gradeLevel]),
        'term' => Term::factory()->for($year)->create(),
        'student' => Student::factory()->create(),
        'subject' => Subject::factory()->create(),
    ];
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student, subject: Subject}  $ctx
 */
function addGrade(array $ctx, int|float $value, EvaluationType $type = EvaluationType::Curenta): void
{
    Grade::factory()->create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $ctx['subject']->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => $value,
        'evaluation_type' => $type,
    ]);
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student, subject: Subject}  $ctx
 */
function termAvg(array $ctx): ?float
{
    $value = TermAverage::query()
        ->where('student_id', $ctx['student']->id)
        ->where('subject_id', $ctx['subject']->id)
        ->where('term_id', $ctx['term']->id)
        ->value('value');

    return $value === null ? null : (float) $value;
}

it('primar: MS = media notelor curente, trunchiat fără rotunjire', function () {
    $ctx = avgSetup(3);
    addGrade($ctx, 9);
    addGrade($ctx, 9);
    addGrade($ctx, 8); // 26/3 = 8.6667 → 8.66 (NU 8.67)

    expect(termAvg($ctx))->toBe(8.66);
});

it('gimnaziu: teza ponderată 50% — MS = (MC + teză)/2', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 8);
    addGrade($ctx, 10); // MC = 9
    addGrade($ctx, 8, EvaluationType::Teza); // (9 + 8) / 2 = 8.5

    expect(termAvg($ctx))->toBe(8.5);
});

it('liceu fără teză: MS = media curentelor', function () {
    $ctx = avgSetup(11);
    addGrade($ctx, 7);
    addGrade($ctx, 8); // 7.5

    expect(termAvg($ctx))->toBe(7.5);
});

it('liceu cu teză: MS = (MC + teză)/2', function () {
    $ctx = avgSetup(12);
    addGrade($ctx, 9);
    addGrade($ctx, 10); // MC = 9.5
    addGrade($ctx, 8, EvaluationType::Teza); // (9.5 + 8)/2 = 8.75

    expect(termAvg($ctx))->toBe(8.75);
});

it('trunchiază fără rotunjire (8.83, nu 8.84)', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 9);
    addGrade($ctx, 9);
    addGrade($ctx, 8); // MC = 8.6667
    addGrade($ctx, 9, EvaluationType::Teza); // (8.6667 + 9)/2 = 8.8333 → 8.83

    expect(termAvg($ctx))->toBe(8.83);
});

it('ESI contează ca notă curentă (în MC)', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 8);
    addGrade($ctx, 10, EvaluationType::Esi); // MC = (8+10)/2 = 9

    expect(termAvg($ctx))->toBe(9.0);
});

it('doar teză, fără note curente', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 9, EvaluationType::Teza);

    expect(termAvg($ctx))->toBe(9.0);
});

it('fără note numerice → media dispare', function () {
    $ctx = avgSetup(7);
    $grade = Grade::factory()->create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $ctx['subject']->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => 8,
    ]);
    expect(termAvg($ctx))->toBe(8.0);

    $grade->forceDelete(); // instanță → declanșează observer-ul

    expect(termAvg($ctx))->toBeNull();
});

it('nota anulată nu contează la medie', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 10); // activă

    Grade::factory()->annulled()->create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $ctx['subject']->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => 2,
    ]);

    expect(termAvg($ctx))->toBe(10.0); // doar nota activă contează
});

it('mai multe sumative → media lor (nu doar prima)', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 8);
    addGrade($ctx, 10); // MC = 9
    addGrade($ctx, 6, EvaluationType::Teza);
    addGrade($ctx, 8, EvaluationType::Teza); // sumativă = (6+8)/2 = 7 → MS = (9+7)/2 = 8

    expect(termAvg($ctx))->toBe(8.0);
});

it('persistă componentele MC și sumativă alături de MS', function () {
    $ctx = avgSetup(7);
    addGrade($ctx, 8);
    addGrade($ctx, 10); // MC = 9
    addGrade($ctx, 7, EvaluationType::Teza); // sumativă = 7 → MS = 8

    $row = TermAverage::query()
        ->where('student_id', $ctx['student']->id)
        ->where('subject_id', $ctx['subject']->id)
        ->where('term_id', $ctx['term']->id)
        ->first();

    expect((float) $row->mc_value)->toBe(9.0)
        ->and((float) $row->summative_value)->toBe(7.0)
        ->and((float) $row->value)->toBe(8.0);
});

it('primar nu are sumativă — o teză aberantă nu se stochează ca sumativă', function () {
    $ctx = avgSetup(3);
    addGrade($ctx, 9);
    addGrade($ctx, 8); // MC = 8.5
    addGrade($ctx, 4, EvaluationType::Teza); // la primar: ignorată (nici în MC, nici sumativă)

    $row = TermAverage::query()
        ->where('student_id', $ctx['student']->id)
        ->where('subject_id', $ctx['subject']->id)
        ->where('term_id', $ctx['term']->id)
        ->first();

    expect((float) $row->value)->toBe(8.5)
        ->and($row->summative_value)->toBeNull();
});
