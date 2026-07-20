<?php

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Filament\Resources\GradeCorrections\Pages\ViewGradeCorrection;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function hwgcUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function gradeForCorrection(int|float $value): Grade
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);

    return Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => Term::factory()->for($year)->create()->id,
        'value' => $value,
    ]);
}

it('aprobarea aplică noua valoare pe notă și recalculează media', function () {
    $grade = gradeForCorrection(5);
    $reviewer = User::factory()->create();

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 5,
        'new_value' => 9,
        'status' => CorrectionStatus::Pending,
    ]);

    $correction->approve($reviewer->id, 'corect');

    $average = TermAverage::query()
        ->where('student_id', $grade->student_id)
        ->where('subject_id', $grade->subject_id)
        ->where('term_id', $grade->term_id)
        ->value('value');

    expect((float) $grade->fresh()->value)->toBe(9.0)
        ->and($correction->fresh()->status)->toBe(CorrectionStatus::Approved)
        ->and((float) $average)->toBe(9.0);
});

it('respingerea nu schimbă nota', function () {
    $grade = gradeForCorrection(5);
    $reviewer = User::factory()->create();

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 5,
        'new_value' => 9,
        'status' => CorrectionStatus::Pending,
    ]);

    $correction->reject($reviewer->id, 'nejustificat');

    expect((float) $grade->fresh()->value)->toBe(5.0)
        ->and($correction->fresh()->status)->toBe(CorrectionStatus::Rejected);
});

it('profesorul vede doar corecțiile sale; administrația pe toate', function () {
    $profA = User::factory()->create();
    $profA->assignRole(UserRole::Profesor->value);
    $profB = User::factory()->create();
    $profB->assignRole(UserRole::Profesor->value);

    $a = GradeCorrection::factory()->create(['requested_by_user_id' => $profA->id]);
    $b = GradeCorrection::factory()->create(['requested_by_user_id' => $profB->id]);

    $this->actingAs($profA);
    expect(GradeCorrectionResource::getEloquentQuery()->pluck('id'))
        ->toContain($a->id)
        ->not->toContain($b->id);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin);
    expect(GradeCorrectionResource::getEloquentQuery()->count())->toBe(2);
});

// ─── Fișa cererii (analiza completă înainte de decizie) ─────────────────────────────────

it('fișa arată valorile față în față, motivul integral și contextul notei', function () {
    $grade = gradeForCorrection(5);
    $reviewer = hwgcUser(UserRole::Director);

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => hwgcUser(UserRole::Profesor)->id,
        'old_value' => 5,
        'new_value' => 9,
        'reason' => 'Motiv lung pe care lista îl trunchia — fișa îl arată integral, fără puncte de suspensie.',
        'status' => CorrectionStatus::Pending,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewGradeCorrection::class, ['record' => $correction->id])
        ->assertSee(__('panel.grade_correction_view.current_value'))
        ->assertSee(__('panel.grade_correction_view.proposed_value'))
        ->assertSee('Motiv lung pe care lista îl trunchia — fișa îl arată integral, fără puncte de suspensie.')
        ->assertSee($grade->student->full_name)
        ->assertSee(__('panel.homework_correction_view.timeline'))
        ->assertSee(__('panel.actions.approve.label'))
        ->assertSee(__('panel.actions.reject.label'));
});

it('istoricul notei apare pe fișă: corecția anterioară + jurnalul de modificări din audit', function () {
    config(['audit.console' => true]);

    $grade = gradeForCorrection(6);
    $reviewer = hwgcUser(UserRole::Director);

    // O modificare DIRECTĂ a valorii → intrare de audit pe notă.
    $this->actingAs($reviewer);
    $grade->update(['value' => 7]);

    // O corecție anterioară, respinsă.
    GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 7,
        'new_value' => 10,
        'reason' => 'Prima încercare.',
        'status' => CorrectionStatus::Rejected,
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => now(),
    ]);

    $current = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 7,
        'new_value' => 8,
        'reason' => 'A doua încercare.',
        'status' => CorrectionStatus::Pending,
    ]);

    Livewire::test(ViewGradeCorrection::class, ['record' => $current->id])
        ->assertSee(__('panel.grade_correction_view.grade_history'))
        // Corecția anterioară, cu schimbarea și verdictul ei.
        ->assertSee('7 → 10')
        // Jurnalul notei: schimbarea directă 6 → 7.
        ->assertSee(__('panel.grade_correction_view.audit_value_changed', ['old' => '6', 'new' => '7']));
});

it('respingerea din fișă CERE motiv; cu motiv → consemnată, nota rămâne neatinsă', function () {
    $grade = gradeForCorrection(4);
    $reviewer = hwgcUser(UserRole::PrimVicedirector);

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 4,
        'new_value' => 7,
        'reason' => 'Transcriere greșită.',
        'status' => CorrectionStatus::Pending,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewGradeCorrection::class, ['record' => $correction->id])
        ->callAction('reject', ['review_note' => ''])
        ->assertHasActionErrors();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Pending);

    Livewire::test(ViewGradeCorrection::class, ['record' => $correction->id])
        ->callAction('reject', ['review_note' => 'Lucrarea confirmă nota inițială.'])
        ->assertNotified();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and($correction->review_note)->toBe('Lucrarea confirmă nota inițială.')
        ->and((float) $grade->fresh()->value)->toBe(4.0);
});

it('aprobarea din fișă aplică valoarea pe notă; judecata dispare după verdict', function () {
    $grade = gradeForCorrection(6);
    $reviewer = hwgcUser(UserRole::Director);

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 6,
        'new_value' => 8,
        'reason' => 'Punctaj recalculat.',
        'status' => CorrectionStatus::Pending,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewGradeCorrection::class, ['record' => $correction->id])
        ->assertActionHidden('withdraw')
        ->callAction('approve', ['review_note' => 'De acord.'])
        ->assertNotified();

    expect((float) $grade->fresh()->value)->toBe(8.0)
        ->and($correction->refresh()->status)->toBe(CorrectionStatus::Approved);

    Livewire::test(ViewGradeCorrection::class, ['record' => $correction->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});

it('fișa e a arhivarilor sau a solicitantului propriu — alt profesor primește 404; judecata lasă audit', function () {
    config(['audit.console' => true]);

    $grade = gradeForCorrection(5);
    $requester = hwgcUser(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $requester->id]);
    $stranger = hwgcUser(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $stranger->id]);
    $reviewer = hwgcUser(UserRole::Director);

    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => $requester->id,
        'old_value' => 5,
        'new_value' => 6,
        'reason' => 'Motiv.',
        'status' => CorrectionStatus::Pending,
    ]);

    // Scoping-ul ascunde cererile străine → 404 (nu confirmă nici existența).
    $this->actingAs($stranger)
        ->get("/admin/grade-corrections/{$correction->id}")
        ->assertNotFound();

    $this->actingAs($requester)
        ->get("/admin/grade-corrections/{$correction->id}")
        ->assertOk();

    // Judecata lasă urmă în jurnalul de audit (modelul e acum auditabil).
    $this->actingAs($reviewer);
    $correction->approve($reviewer->id, 'Ok.');

    expect(DB::table('audits')
        ->where('auditable_type', GradeCorrection::class)
        ->where('auditable_id', $correction->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();
});
