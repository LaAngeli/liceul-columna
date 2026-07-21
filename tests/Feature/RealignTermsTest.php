<?php

/**
 * Comanda `app:realign-terms` (calea manuală a realinierii pe care TermObserver o rulează la
 * mutarea intervalelor): notele/absențele cu term_id inconsecvent cu data lor sunt mutate la
 * semestrul DAT DE DATĂ; cele datate în afara oricărui semestru rămân pe loc (nu se orfanizează).
 */

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30']);
    $this->semI = Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => false,
    ]);
    $this->semII = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->subject = Subject::factory()->create();
});

function driftGrade(mixed $ctx, int $termId, string $date): Grade
{
    return Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => $ctx->subject->id,
        'school_class_id' => $ctx->class->id,
        'term_id' => $termId,
        'graded_on' => $date,
        'value' => 8,
    ]);
}

it('mută nota datată în alt semestru la semestrul dat de dată; lasă pe loc pe cea fără semestru-țintă', function () {
    // Corect ancorată (Sem I, dec) — nu se mișcă.
    $aligned = driftGrade($this, $this->semI->id, '2025-12-10');
    // Etichetată Sem I dar datată în februarie → aparține Sem II.
    $drifted = driftGrade($this, $this->semI->id, '2026-02-15');
    // Datată în iulie (după 30.06) → niciun semestru → RĂMÂNE pe loc.
    $noTarget = driftGrade($this, $this->semI->id, '2026-07-05');

    $this->artisan('app:realign-terms')->assertSuccessful();

    expect($aligned->fresh()->term_id)->toBe($this->semI->id)
        ->and($drifted->fresh()->term_id)->toBe($this->semII->id)
        ->and($noTarget->fresh()->term_id)->toBe($this->semI->id);
});

it('dry-run raportează ce s-ar muta fără să scrie', function () {
    $drifted = driftGrade($this, $this->semI->id, '2026-03-01');
    driftGrade($this, $this->semI->id, '2026-07-20'); // fără semestru-țintă

    $this->artisan('app:realign-terms', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    // Nimic nu s-a mutat.
    expect($drifted->fresh()->term_id)->toBe($this->semI->id);
});

it('este idempotentă — a doua rulare mută 0', function () {
    driftGrade($this, $this->semI->id, '2026-02-15');

    $this->artisan('app:realign-terms')->assertSuccessful();
    $this->artisan('app:realign-terms')
        ->expectsOutputToContain('mutat 0 note')
        ->assertSuccessful();
});
