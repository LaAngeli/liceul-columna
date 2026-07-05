<?php

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Schemas\AbsenceForm;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create(['number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true]);

    // Profesor: diriginte al clasei A, dar doar profesor de Chimie la clasa B.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->user = $user;

    $this->classHomeroom = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $this->teacher->id]);
    $this->classOther = SchoolClass::factory()->for($this->year)->create();

    $this->chimie = Subject::factory()->create(['name' => 'Chimie']);
    $this->biologie = Subject::factory()->create(['name' => 'Biologie']);

    // La clasa B: el predă Chimie; altcineva predă Biologie.
    $otherTeacher = Teacher::factory()->create();
    TeachingAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'subject_id' => $this->chimie->id, 'school_class_id' => $this->classOther->id]);
    TeachingAssignment::factory()->create(['teacher_id' => $otherTeacher->id, 'subject_id' => $this->biologie->id, 'school_class_id' => $this->classOther->id]);

    $this->actingAs($user);
});

/**
 * @return array<int, string>
 */
function subjectOptionsFor(?int $classId): array
{
    $method = new ReflectionMethod(AbsenceForm::class, 'subjectOptions');
    $method->setAccessible(true);

    /** @var array<int, string> $options */
    $options = $method->invoke(null, $classId);

    return $options;
}

it('la o clasă unde e DOAR profesor, dropdown-ul arată doar disciplinele LUI (nu toate ale clasei)', function () {
    // Bug-ul original: dirigintele (undeva) vedea TOATE disciplinele oricărei clase → putea alege una
    // pe care n-o predă → respins tăcut la submit.
    expect(array_keys(subjectOptionsFor($this->classOther->id)))->toBe([$this->chimie->id]);
});

it('la clasa unde e DIRIGINTE, dropdown-ul arată toate disciplinele predate în clasă', function () {
    $otherTeacher = Teacher::factory()->create();
    TeachingAssignment::factory()->create(['teacher_id' => $otherTeacher->id, 'subject_id' => $this->biologie->id, 'school_class_id' => $this->classHomeroom->id]);
    TeachingAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'subject_id' => $this->chimie->id, 'school_class_id' => $this->classHomeroom->id]);

    $keys = array_keys(subjectOptionsFor($this->classHomeroom->id));

    expect($keys)->toContain($this->biologie->id)->toContain($this->chimie->id);
});

it('eroarea de scope (duplicat) se AFIȘEAZĂ pe câmp — nu „nu se întâmplă nimic" (prefix data.)', function () {
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($this->classOther)->for($this->year)->create();

    $payload = [
        'school_class_id' => $this->classOther->id,
        'subject_id' => $this->chimie->id, // o predă → trece de validarea Select-ului
        'student_id' => $student->id,
        'occurred_on' => '2026-03-10',
    ];

    // Prima absență reușește.
    Livewire::test(CreateAbsence::class)->fillForm($payload)->call('create')->assertHasNoFormErrors();

    // A doua (duplicat) e respinsă de enforceAbsenceScope, iar eroarea e VIZIBILĂ pe câmp (prefix data.).
    Livewire::test(CreateAbsence::class)->fillForm($payload)->call('create')->assertHasFormErrors(['student_id']);

    expect(Absence::query()->count())->toBe(1);
});
