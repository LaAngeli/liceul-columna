<?php

use App\Enums\AcademicRecordPeriod;
use App\Enums\UserRole;
use App\Filament\Resources\AcademicRecords\AcademicRecordResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
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

function adminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    return $admin;
}

// ---- Foaie matricolă (academic_records) ----

it('dirigintele vede foaia matricolă completă a elevilor din clasa lui', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create(['grade_level' => 8, 'section' => '1']);
    $other = SchoolClass::factory()->for($year)->create(['grade_level' => 8, 'section' => '2']);

    $myStudent = Student::factory()->create();
    Enrollment::factory()->for($myStudent)->for($homeroom)->for($year)->create();
    $otherStudent = Student::factory()->create();
    Enrollment::factory()->for($otherStudent)->for($other)->for($year)->create();

    $subjX = Subject::factory()->create();
    $subjY = Subject::factory()->create();
    $mine1 = AcademicRecord::factory()->create(['student_id' => $myStudent->id, 'subject_id' => $subjX->id]);
    $mine2 = AcademicRecord::factory()->create(['student_id' => $myStudent->id, 'subject_id' => $subjY->id]);
    $notMine = AcademicRecord::factory()->create(['student_id' => $otherStudent->id, 'subject_id' => $subjX->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($user);

    expect(AcademicRecordResource::getEloquentQuery()->pluck('id'))
        ->toContain($mine1->id, $mine2->id)
        ->not->toContain($notMine->id);
});

it('profesorul vede din foaia matricolă doar disciplina pe care o predă', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $subjX = Subject::factory()->create();
    $subjY = Subject::factory()->create();
    $recX = AcademicRecord::factory()->create(['student_id' => $student->id, 'subject_id' => $subjX->id]);
    $recY = AcademicRecord::factory()->create(['student_id' => $student->id, 'subject_id' => $subjY->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subjX->id,
        'school_class_id' => $class->id,
    ]);

    $this->actingAs($user);

    expect(AcademicRecordResource::getEloquentQuery()->pluck('id'))
        ->toContain($recX->id)
        ->not->toContain($recY->id);
});

it('foaia matricolă e read-only și vizibilă integral administrației', function () {
    AcademicRecord::factory()->count(3)->create();

    $this->actingAs(adminUser());

    expect(AcademicRecordResource::getEloquentQuery()->count())->toBe(3)
        ->and(AcademicRecordResource::canCreate())->toBeFalse();
});

it('profesorul fără repartizări nu vede nicio foaie matricolă', function () {
    AcademicRecord::factory()->count(2)->create();

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect(AcademicRecordResource::getEloquentQuery()->count())->toBe(0);
});

// ---- Teme (homework_assignments) ----

it('profesorul vede temele claselor lui și temele proprii, nu și altele', function () {
    $year = AcademicYear::factory()->create();
    $myClass = SchoolClass::factory()->for($year)->create(['grade_level' => 9, 'section' => 'A']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $subject = Subject::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'school_class_id' => $myClass->id,
    ]);

    $forMyClass = HomeworkAssignment::factory()->create(['grade_level' => 9, 'section' => 'A', 'teacher_id' => null]);
    $mineAuthored = HomeworkAssignment::factory()->create(['grade_level' => 11, 'section' => 'B', 'teacher_id' => $teacher->id]);
    $unrelated = HomeworkAssignment::factory()->create(['grade_level' => 11, 'section' => 'B', 'teacher_id' => null]);

    $this->actingAs($user);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id'))
        ->toContain($forMyClass->id, $mineAuthored->id)
        ->not->toContain($unrelated->id);
});

it('profesorul poate edita doar temele proprii; administrația oricare', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    $own = HomeworkAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $foreign = HomeworkAssignment::factory()->create(['teacher_id' => null]);

    $this->actingAs($user);
    expect(HomeworkAssignmentResource::canEdit($own))->toBeTrue()
        ->and(HomeworkAssignmentResource::canEdit($foreign))->toBeFalse();

    $this->actingAs(adminUser());
    expect(HomeworkAssignmentResource::canEdit($foreign))->toBeTrue();
});

it('castează corect period (enum) și links (array)', function () {
    $rec = AcademicRecord::factory()->create(['period' => AcademicRecordPeriod::Annual->value]);
    expect($rec->refresh()->period)->toBe(AcademicRecordPeriod::Annual);

    $hw = HomeworkAssignment::factory()->create(['links' => ['https://a.test', 'https://b.test']]);
    expect($hw->refresh()->links)->toBe(['https://a.test', 'https://b.test']);
});
