<?php

use App\Actions\ComputeTermAverage;
use App\Enums\EvaluationType;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nota individuală e ÎNTREAGĂ pe scala 1–10; zecimalele aparțin exclusiv mediilor (§1/§2.4).
 *
 * Regula a fost încălcată în practică: două generatoare de demo compuneau nota ca
 * `random_int(50, 100) / 10`, iar valori de tipul 6,5 au ajuns în cabinetul familiei. Testele de
 * aici apără atât garda, cât și unealta care a reparat datele.
 */

/** @return array{student: Student, subject: Subject, class: SchoolClass, term: Term} */
function integerGradeFixture(): array
{
    $class = SchoolClass::factory()->create();
    $term = Term::factory()->create(['is_current' => true]);

    return [
        'student' => Student::factory()->create(),
        'subject' => Subject::factory()->create(),
        'class' => $class,
        'term' => $term,
    ];
}

it('modelul REFUZĂ o notă cu zecimale, oricare ar fi calea de scriere', function () {
    $fx = integerGradeFixture();

    expect(fn () => Grade::factory()->create([
        'student_id' => $fx['student']->id,
        'subject_id' => $fx['subject']->id,
        'school_class_id' => $fx['class']->id,
        'term_id' => $fx['term']->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 6.5,
    ]))->toThrow(ValidationException::class);

    expect(Grade::query()->count())->toBe(0);
});

it('acceptă întregii de pe toată scala, inclusiv capetele', function () {
    $fx = integerGradeFixture();

    foreach ([1, 5, 10] as $value) {
        Grade::factory()->create([
            'student_id' => $fx['student']->id,
            'subject_id' => $fx['subject']->id,
            'school_class_id' => $fx['class']->id,
            'term_id' => $fx['term']->id,
            'evaluation_type' => EvaluationType::Curenta,
            'value' => $value,
        ]);
    }

    expect(Grade::query()->count())->toBe(3);
});

it('nu se împiedică de calificative (primar) — acolo valoarea numerică lipsește', function () {
    $fx = integerGradeFixture();

    Grade::factory()->create([
        'student_id' => $fx['student']->id,
        'subject_id' => $fx['subject']->id,
        'school_class_id' => $fx['class']->id,
        'term_id' => $fx['term']->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => null,
        'calificativ' => 'FB',
    ]);

    expect(Grade::query()->count())->toBe(1);
});

it('comanda de reparare rotunjește notele vechi și recalculează media', function () {
    $fx = integerGradeFixture();

    // Scriem prin query builder ca să simulăm exact ce a produs generatorul demo: garda de model
    // n-ar mai permite azi astfel de rânduri.
    foreach ([6.5, 7.4, 9.5] as $value) {
        DB::table('grades')->insert([
            'student_id' => $fx['student']->id,
            'subject_id' => $fx['subject']->id,
            'school_class_id' => $fx['class']->id,
            'term_id' => $fx['term']->id,
            'graded_on' => '2026-02-02',
            'type' => 1,
            'evaluation_type' => EvaluationType::Curenta->value,
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // DRY-RUN: raportează, dar nu atinge nimic.
    $this->artisan('app:fix-decimal-grades')->assertSuccessful();
    expect(DB::table('grades')->whereRaw('value <> ROUND(value)')->count())->toBe(3);

    $this->artisan('app:fix-decimal-grades --apply')->assertSuccessful();

    $values = DB::table('grades')->orderBy('id')->pluck('value')
        ->map(fn ($v): float => (float) $v)->all();

    // 6,5 → 7 și 9,5 → 10 (rotunjire la cel mai apropiat, jumătatea în sus); 7,4 → 7.
    expect($values)->toBe([7.0, 7.0, 10.0])
        ->and(DB::table('grades')->whereRaw('value <> ROUND(value)')->count())->toBe(0);

    // Media semestrială a fost RECALCULATĂ din valorile noi: (7+7+10)/3 = 8, nu (6,5+7,4+9,5)/3.
    $expected = app(ComputeTermAverage::class)
        ->execute($fx['student']->id, $fx['subject']->id, $fx['term']->id);

    expect((float) $expected->value)->toBe(8.0);
});

it('a doua rulare nu mai găsește nimic', function () {
    integerGradeFixture();

    $this->artisan('app:fix-decimal-grades --apply')
        ->expectsOutputToContain('Nicio notă cu zecimale')
        ->assertSuccessful();
});
