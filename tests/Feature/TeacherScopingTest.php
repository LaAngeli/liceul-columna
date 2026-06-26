<?php

use App\Enums\UserRole;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/**
 * Creează un cont de profesor legat de o fișă, cu repartizare la clasele date.
 */
function profesorPredandLa(SchoolClass ...$classes): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $subject = Subject::factory()->create();

    foreach ($classes as $class) {
        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
        ]);
    }

    return $user;
}

it('profesorul vede doar clasele lui; administrația le vede pe toate', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();

    $this->actingAs(profesorPredandLa($classA));
    $visible = SchoolClassResource::getEloquentQuery()->pluck('id');
    expect($visible)->toContain($classA->id)->not->toContain($classB->id);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin);
    expect(SchoolClassResource::getEloquentQuery()->count())->toBe(2);
});

it('profesorul vede doar elevii din clasele lui', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();

    $studentA = Student::factory()->create();
    Enrollment::factory()->for($studentA)->for($classA)->for($year)->create();
    $studentB = Student::factory()->create();
    Enrollment::factory()->for($studentB)->for($classB)->for($year)->create();

    $this->actingAs(profesorPredandLa($classA));
    $ids = StudentResource::getEloquentQuery()->pluck('id');
    expect($ids)->toContain($studentA->id)->not->toContain($studentB->id);
});

it('resursele de administrație sunt ascunse profesorilor', function () {
    $this->actingAs(profesorPredandLa());
    expect(AcademicYearResource::canAccess())->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin);
    expect(AcademicYearResource::canAccess())->toBeTrue();
});

it('profesorul nu poate crea elevi/clase (read-only); administrația poate', function () {
    $this->actingAs(profesorPredandLa());
    expect(StudentResource::canCreate())->toBeFalse()
        ->and(SchoolClassResource::canCreate())->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin);
    expect(StudentResource::canCreate())->toBeTrue();
});

it('dirigintele vede TOATĂ clasa lui, chiar fără să predea acolo', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create();
    $otherClass = SchoolClass::factory()->for($year)->create();

    $inHomeroom = Student::factory()->create();
    Enrollment::factory()->for($inHomeroom)->for($homeroom)->for($year)->create();
    $elsewhere = Student::factory()->create();
    Enrollment::factory()->for($elsewhere)->for($otherClass)->for($year)->create();

    // Diriginte FĂRĂ repartizare în clasa lui — accesul vine doar din calitatea de diriginte.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($user);

    expect(StudentResource::getEloquentQuery()->pluck('id'))
        ->toContain($inHomeroom->id)->not->toContain($elsewhere->id)
        ->and(SchoolClassResource::getEloquentQuery()->pluck('id'))
        ->toContain($homeroom->id)->not->toContain($otherClass->id);
});

it('profesorul vede notele DOAR de la (clasa, disciplina) lui', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();
    $subjX = Subject::factory()->create();
    $subjY = Subject::factory()->create();

    $gAX = Grade::factory()->create(['school_class_id' => $classA->id, 'subject_id' => $subjX->id]);
    $gAY = Grade::factory()->create(['school_class_id' => $classA->id, 'subject_id' => $subjY->id]);
    $gBX = Grade::factory()->create(['school_class_id' => $classB->id, 'subject_id' => $subjX->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subjX->id,
        'school_class_id' => $classA->id,
    ]);

    $this->actingAs($user);

    expect(GradeResource::getEloquentQuery()->pluck('id'))
        ->toContain($gAX->id)              // clasa lui + disciplina lui
        ->not->toContain($gAY->id)         // clasa lui, dar altă disciplină
        ->not->toContain($gBX->id);        // disciplina lui, dar altă clasă
});

it('dirigintele vede TOATE notele clasei lui (orice disciplină)', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create();
    $other = SchoolClass::factory()->for($year)->create();
    $subjX = Subject::factory()->create();
    $subjY = Subject::factory()->create();

    $g1 = Grade::factory()->create(['school_class_id' => $homeroom->id, 'subject_id' => $subjX->id]);
    $g2 = Grade::factory()->create(['school_class_id' => $homeroom->id, 'subject_id' => $subjY->id]);
    $gOther = Grade::factory()->create(['school_class_id' => $other->id, 'subject_id' => $subjX->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($user);

    expect(GradeResource::getEloquentQuery()->pluck('id'))
        ->toContain($g1->id, $g2->id)
        ->not->toContain($gOther->id);
});

it('poate nota DOAR la (clasa, disciplina) pe care o predă', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $other = SchoolClass::factory()->for($year)->create();
    $subjX = Subject::factory()->create();
    $subjY = Subject::factory()->create();

    $teacher = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subjX->id,
        'school_class_id' => $class->id,
    ]);

    expect($teacher->canGradeClassSubject($class->id, $subjX->id))->toBeTrue()
        ->and($teacher->canGradeClassSubject($class->id, $subjY->id))->toBeFalse()
        ->and($teacher->canGradeClassSubject($other->id, $subjX->id))->toBeFalse();
});

it('dirigintele poate înregistra absențe în clasa lui chiar fără să predea acolo', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create();
    $subject = Subject::factory()->create();

    $teacher = Teacher::factory()->create();
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    // poate înregistra absențe (diriginte), dar NU poate nota (nu predă disciplina)
    expect($teacher->canRecordAbsence($homeroom->id, $subject->id))->toBeTrue()
        ->and($teacher->canGradeClassSubject($homeroom->id, $subject->id))->toBeFalse();
});

it('dirigintele vede TOATE absențele clasei lui', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create();
    $other = SchoolClass::factory()->for($year)->create();

    $a1 = Absence::factory()->create(['school_class_id' => $homeroom->id]);
    $a2 = Absence::factory()->create(['school_class_id' => $homeroom->id]);
    $aOther = Absence::factory()->create(['school_class_id' => $other->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($user);

    expect(AbsenceResource::getEloquentQuery()->pluck('id'))
        ->toContain($a1->id, $a2->id)
        ->not->toContain($aOther->id);
});
