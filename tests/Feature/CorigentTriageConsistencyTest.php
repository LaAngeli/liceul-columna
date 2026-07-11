<?php

/**
 * Triajul „corigenți" — sursă UNICĂ pentru contorul de dashboard ({@see NeedsAttention}) și filtrul
 * din tabelul de elevi ({@see StudentsTable}): {@see Student::scopeCorigentInTerm}. Aceste teste fixează
 * regulile pe care cele două suprafețe TREBUIE să le împartă, ca să nu se mai contrazică între ele
 * sau cu statutul din cabinet:
 *  - pragul vine din constanta {@see Grades::PASS} (nu literalul „5");
 *  - o medie restantă LICHIDATĂ printr-un examen de corigență PROMOVAT nu mai numără drept corigent
 *    (altfel dashboard-ul ar arăta „corigent" un elev pe care cabinetul îl arată „Promovat").
 */

use App\Enums\CorigentaSeason;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\Grades;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
});

/** Creează o medie semestrială pentru un elev nou, în semestrul curent. */
function averageFor(float $value, $test): Student
{
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();
    TermAverage::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $test->class->id,
        'term_id' => $test->term->id,
        'value' => $value,
    ]);

    return $student;
}

it('numără drept corigent un elev cu media semestrială sub pragul de promovare', function () {
    $failing = averageFor(4.50, $this);
    $passing = averageFor(7.00, $this);

    $corigenti = Student::query()->corigentInTerm($this->term->id)->pluck('id');

    expect($corigenti)->toContain($failing->id)
        ->and($corigenti)->not->toContain($passing->id);
});

it('pragul e constanta Grades::PASS: media exact la prag NU e corigentă, media sub prag e', function () {
    $atPass = averageFor(Grades::PASS, $this);        // 5.00 → promovat
    $belowPass = averageFor(Grades::PASS - 0.01, $this); // 4.99 → corigent

    $corigenti = Student::query()->corigentInTerm($this->term->id)->pluck('id');

    expect($corigenti)->toContain($belowPass->id)
        ->and($corigenti)->not->toContain($atPass->id);
});

it('o medie restantă LICHIDATĂ printr-un examen de corigență promovat NU mai numără drept corigent', function () {
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();
    TermAverage::factory()->create([
        'student_id' => $student->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 3.00,
    ]);

    // Examen de corigență PROMOVAT (notă ≥ prag) pe aceeași disciplină + semestru.
    CorigentaExam::create([
        'student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara, 'mark' => 6.00,
    ]);

    expect(Student::query()->corigentInTerm($this->term->id)->pluck('id'))->not->toContain($student->id)
        ->and(Student::query()->notCorigentInTerm($this->term->id)->pluck('id'))->toContain($student->id);
});

it('o medie restantă cu examen de corigență PICAT sau neexaminat rămâne corigentă', function () {
    $failedExam = Student::factory()->create();
    $unmarkedExam = Student::factory()->create();
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();

    foreach ([[$failedExam, $subjectA, 4.00], [$unmarkedExam, $subjectB, null]] as [$student, $subject, $mark]) {
        TermAverage::factory()->create([
            'student_id' => $student->id, 'subject_id' => $subject->id,
            'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 3.00,
        ]);
        CorigentaExam::create([
            'student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $this->term->id,
            'season' => CorigentaSeason::Vara, 'mark' => $mark,
        ]);
    }

    $corigenti = Student::query()->corigentInTerm($this->term->id)->pluck('id');

    expect($corigenti)->toContain($failedExam->id)   // examen picat (4 < 5) → încă restant
        ->and($corigenti)->toContain($unmarkedExam->id); // neexaminat (mark null) → încă restant
});

it('fără semestru curent (termId null) predicatul e neutru — nu filtrează nimic', function () {
    averageFor(3.00, $this);

    // termId null → scope neutru: setul rămâne întreg (toți elevii), nu doar corigenții.
    expect(Student::query()->corigentInTerm(null)->count())->toBe(Student::query()->count());
});
