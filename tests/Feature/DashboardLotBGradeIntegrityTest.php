<?php

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Filament\Resources\Grades\Pages\ListGrades;
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
});

function lotBGradingTeacher(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

// ─── Î-3: nota cu dată în viitor respinsă (server) ──────────────────────────────────────

it('nota cu dată în VIITOR e respinsă pe server, iar eroarea apare pe câmp', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();

    actingAs(lotBGradingTeacher($class, $subject));

    Livewire::test(CreateGrade::class)
        ->fillForm([
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'evaluation_type' => EvaluationType::Curenta->value,
            'graded_on' => now()->addWeek()->toDateString(),
            'value' => 9,
        ])
        ->call('create')
        ->assertHasFormErrors(['graded_on']);

    expect(Grade::query()->count())->toBe(0);
});

it('nota cu dată în trecut/prezent se acceptă', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();

    actingAs(lotBGradingTeacher($class, $subject));

    Livewire::test(CreateGrade::class)
        ->fillForm([
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'evaluation_type' => EvaluationType::Curenta->value,
            'graded_on' => '2026-03-10',
            'value' => 9,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Grade::query()->count())->toBe(1);
});

// ─── M-1: dirigintele nu anulează note la discipline pe care nu le predă ─────────────────

it('dirigintele poate anula nota la disciplina LUI, dar nu la o disciplină pe care n-o predă', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $chimie = Subject::factory()->create(['name' => 'Chimie', 'grading_type' => GradingType::Numeric]);
    $biologie = Subject::factory()->create(['name' => 'Biologie', 'grading_type' => GradingType::Numeric]);

    // Diriginte al clasei, predă DOAR Chimie.
    $dirigUser = User::factory()->create();
    $dirigUser->assignRole(UserRole::Diriginte->value);
    $dirig = Teacher::factory()->create(['user_id' => $dirigUser->id]);
    $class->update(['homeroom_teacher_id' => $dirig->id]);
    TeachingAssignment::factory()->create(['teacher_id' => $dirig->id, 'school_class_id' => $class->id, 'subject_id' => $chimie->id]);

    // Alt profesor predă Biologie în aceeași clasă.
    $other = Teacher::factory()->create();
    TeachingAssignment::factory()->create(['teacher_id' => $other->id, 'school_class_id' => $class->id, 'subject_id' => $biologie->id]);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();

    $chimieGrade = Grade::factory()->create([
        'school_class_id' => $class->id, 'subject_id' => $chimie->id, 'teacher_id' => $dirig->id,
        'student_id' => $student->id, 'term_id' => $this->term->id, 'value' => 8, 'graded_on' => '2026-03-10',
    ]);
    $biologieGrade = Grade::factory()->create([
        'school_class_id' => $class->id, 'subject_id' => $biologie->id, 'teacher_id' => $other->id,
        'student_id' => $student->id, 'term_id' => $this->term->id, 'value' => 7, 'graded_on' => '2026-03-11',
    ]);

    actingAs($dirigUser);

    Livewire::test(ListGrades::class)
        ->assertTableActionVisible('annul', $chimieGrade)   // predă Chimie → poate anula
        ->assertTableActionHidden('annul', $biologieGrade); // nu predă Biologie → ascuns
});

// ─── M-5: modalul de corecție folosește disciplina notei (câmp + interval) ──────────────

it('solicitarea de corecție se deschide cu câmpul disciplinei notei și creează cererea', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();

    $teacherUser = lotBGradingTeacher($class, $subject);
    $teacher = $teacherUser->teacher;

    $grade = Grade::factory()->create([
        'school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        'student_id' => $student->id, 'term_id' => $this->term->id, 'value' => 6, 'graded_on' => '2026-03-10',
    ]);

    actingAs($teacherUser);

    Livewire::test(ListGrades::class)
        ->callTableAction('requestCorrection', $grade, ['new_value' => 9, 'reason' => 'greșeală de transcriere'])
        ->assertHasNoTableActionErrors();

    expect(GradeCorrection::query()->where('grade_id', $grade->id)->count())->toBe(1);
});
