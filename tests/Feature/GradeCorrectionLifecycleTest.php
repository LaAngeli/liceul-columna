<?php

/**
 * Ciclul de viață al unei cereri de corecție, din auditul live al panoului staff:
 * o notă nu poate avea două cereri în așteptare, anularea notei lasă cererea fără obiect,
 * iar solicitantul o poate retrage cât timp nu a fost judecată. Nimic nu se șterge (§1).
 */

use App\Enums\CorrectionStatus;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GradeCorrections\Pages\ListGradeCorrections;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\Column;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    $this->profesor = User::factory()->create();
    $this->profesor->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->grade = Grade::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->teacher->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 7,
        'calificativ' => null,
    ]);
});

function pendingCorrection(mixed $ctx): GradeCorrection
{
    return GradeCorrection::factory()->create([
        'grade_id' => $ctx->grade->id,
        'requested_by_user_id' => $ctx->profesor->id,
        'old_value' => 7,
        'new_value' => 9,
        'status' => CorrectionStatus::Pending,
    ]);
}

// ─── O singură cerere în așteptare pe notă ──────────────────────────────────────────────

it('o notă cu o corecție în așteptare nu mai oferă acțiunea de solicitare', function () {
    actingAs($this->profesor);

    Livewire::test(ListGrades::class)->assertTableActionVisible('requestCorrection', $this->grade);

    pendingCorrection($this);

    Livewire::test(ListGrades::class)->assertTableActionHidden('requestCorrection', $this->grade);
});

it('nota cu o corecție în așteptare e marcată cu iconița de ceas', function () {
    actingAs($this->profesor);

    $iconOf = fn (Column $column, Grade $record): mixed => $column->record($record)->getIcon($record->value);

    Livewire::test(ListGrades::class)
        ->assertTableColumnExists('value', fn (Column $c) => $iconOf($c, $this->grade) === null, $this->grade);

    pendingCorrection($this);

    Livewire::test(ListGrades::class)
        ->assertTableColumnExists('value', fn (Column $c) => $iconOf($c, $this->grade->fresh()) === 'heroicon-o-clock', $this->grade);
});

/**
 * Filament reevaluează `visible()` și la execuția acțiunii, deci UI-ul singur oprește deja cursa
 * (modală deschisă înainte ca cererea concurentă să apară). Invariantul stă totuși lângă model:
 * nicio cale — seeder, import, API viitor — nu poate crea a doua cerere în așteptare.
 */
it('modala deschisă înainte de apariția unei cereri concurente nu mai poate crea a doua', function () {
    actingAs($this->profesor);

    $component = Livewire::test(ListGrades::class)
        ->mountAction(TestAction::make('requestCorrection')->table($this->grade));

    pendingCorrection($this);

    $component
        ->setActionData(['new_value' => 10, 'reason' => 'Cerere depusă în cursă.'])
        ->callMountedAction();

    expect(GradeCorrection::query()->count())->toBe(1);
});

it('modelul refuză a doua cerere în așteptare, indiferent de calea de creare', function () {
    pendingCorrection($this);

    expect(fn () => GradeCorrection::create([
        'grade_id' => $this->grade->id,
        'requested_by_user_id' => $this->profesor->id,
        'old_value' => 7,
        'new_value' => 10,
        'reason' => 'Ocolind interfața.',
        'status' => CorrectionStatus::Pending,
    ]))->toThrow(ValidationException::class);

    expect(GradeCorrection::query()->count())->toBe(1);
});

it('o cerere nouă e permisă după ce precedenta a fost soluționată', function () {
    pendingCorrection($this)->reject($this->profesor->id, 'Respinsă.');

    GradeCorrection::create([
        'grade_id' => $this->grade->id,
        'requested_by_user_id' => $this->profesor->id,
        'old_value' => 7,
        'new_value' => 8,
        'reason' => 'A doua încercare, după respingere.',
        'status' => CorrectionStatus::Pending,
    ]);

    expect(GradeCorrection::query()->count())->toBe(2);
});

it('după soluționarea cererii, nota redevine disponibilă pentru o corecție nouă', function () {
    $correction = pendingCorrection($this);
    $correction->reject($this->profesor->id, 'Nu se justifică.');

    expect($this->grade->fresh()->hasPendingCorrection())->toBeFalse();
});

// ─── Anularea notei lasă cererea fără obiect ────────────────────────────────────────────

it('anularea notei face caduce cererile de corecție în așteptare', function () {
    $correction = pendingCorrection($this);

    $this->grade->update([
        'annulled_at' => now(),
        'annulled_by_user_id' => $this->profesor->id,
        'annulment_reason' => 'Notă introdusă din greșeală.',
    ]);

    expect($correction->fresh()->status)->toBe(CorrectionStatus::Expired);
});

it('anularea notei nu atinge cererile deja soluționate', function () {
    $correction = pendingCorrection($this);
    $correction->reject($this->profesor->id, 'Respinsă.');

    $this->grade->update(['annulled_at' => now(), 'annulment_reason' => 'Anulare ulterioară.']);

    expect($correction->fresh()->status)->toBe(CorrectionStatus::Rejected);
});

// ─── Retragerea cererii de către solicitant ─────────────────────────────────────────────

it('solicitantul își retrage cererea în așteptare, iar aceasta rămâne în arhivă', function () {
    $correction = pendingCorrection($this);

    actingAs($this->profesor);

    Livewire::test(ListGradeCorrections::class)
        ->callTableAction('withdraw', $correction);

    $correction->refresh();

    expect($correction->status)->toBe(CorrectionStatus::Withdrawn)
        ->and(GradeCorrection::query()->count())->toBe(1)
        ->and($this->grade->fresh()->hasPendingCorrection())->toBeFalse();
});

it('retragerea apare doar pe cererea proprie în așteptare, nu pe una soluționată', function () {
    $aMea = pendingCorrection($this);
    $soluționată = GradeCorrection::factory()->create([
        'grade_id' => $this->grade->id,
        'requested_by_user_id' => $this->profesor->id,
        'status' => CorrectionStatus::Rejected,
    ]);

    actingAs($this->profesor);

    Livewire::test(ListGradeCorrections::class)
        ->assertTableActionVisible('withdraw', $aMea)
        ->assertTableActionHidden('withdraw', $soluționată);
});

/** Cererile colegilor nici nu ajung în lista profesorului — arhiva e scoped pe solicitant. */
it('profesorul nu vede deloc cererile altui profesor', function () {
    $altProfesor = User::factory()->create();
    $altProfesor->assignRole(UserRole::Profesor->value);

    $aAltuia = GradeCorrection::factory()->create([
        'grade_id' => $this->grade->id,
        'requested_by_user_id' => $altProfesor->id,
        'status' => CorrectionStatus::Pending,
    ]);

    actingAs($this->profesor);

    Livewire::test(ListGradeCorrections::class)->assertCanNotSeeTableRecords([$aAltuia]);
});

// ─── Motivarea urmează data absenței ────────────────────────────────────────────────────

it('mutarea datei scoate absența de sub o dovadă care nu o mai acoperă', function () {
    $absence = Absence::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->teacher->id,
        'occurred_on' => '2026-03-10',
        'is_motivated' => true,
    ]);

    AbsenceMotivation::factory()->create([
        'student_id' => $this->student->id,
        'status' => RequestStatus::Approved,
        'period_start' => '2026-03-10',
        'period_end' => '2026-03-10',
    ]);

    expect($absence->hasApprovedMotivationOn('2026-03-10'))->toBeTrue()
        ->and($absence->hasApprovedMotivationOn('2026-03-09'))->toBeFalse();
});
