<?php

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

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
