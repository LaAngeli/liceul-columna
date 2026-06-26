<?php

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('familia depune o cerere de motivare din cabinet', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)
        ->post("/cabinet/elev/{$student->id}/motivare", [
            'reason' => 'Gripă, recomandare medicală',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-03',
        ])
        ->assertRedirect();

    expect(AbsenceMotivation::query()
        ->where('student_id', $student->id)
        ->where('status', RequestStatus::Pending)
        ->count())->toBe(1);
});

it('un cont fără legătură nu poate depune cerere pentru un elev', function () {
    $student = Student::factory()->create();
    $other = User::factory()->create();
    $other->assignRole(UserRole::Parinte->value);

    $this->actingAs($other)
        ->post("/cabinet/elev/{$student->id}/motivare", [
            'reason' => 'x',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-01',
        ])
        ->assertForbidden();
});

it('validarea marchează ca motivate absențele din perioada cerută', function () {
    $student = Student::factory()->create();
    $inPeriod = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    $outOfPeriod = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-10', 'is_motivated' => false]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
    ]);
    $diriginte = User::factory()->create();

    $motivation->approve($diriginte->id);

    expect($inPeriod->fresh()->is_motivated)->toBeTrue()
        ->and($outOfPeriod->fresh()->is_motivated)->toBeFalse()
        ->and($motivation->fresh()->status)->toBe(RequestStatus::Approved);
});

it('respingerea nu schimbă absențele', function () {
    $student = Student::factory()->create();
    $absence = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
    ]);

    $motivation->reject(User::factory()->create()->id, 'fără justificativ');

    expect($absence->fresh()->is_motivated)->toBeFalse()
        ->and($motivation->fresh()->status)->toBe(RequestStatus::Rejected);
});

it('dirigintele vede doar cererile elevilor din clasa lui', function () {
    $year = AcademicYear::factory()->create();
    $homeroom = SchoolClass::factory()->for($year)->create();
    $other = SchoolClass::factory()->for($year)->create();

    $myStudent = Student::factory()->create();
    Enrollment::factory()->for($myStudent)->for($homeroom)->for($year)->create();
    $otherStudent = Student::factory()->create();
    Enrollment::factory()->for($otherStudent)->for($other)->for($year)->create();

    $mine = AbsenceMotivation::factory()->create(['student_id' => $myStudent->id]);
    $notMine = AbsenceMotivation::factory()->create(['student_id' => $otherStudent->id]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $homeroom->update(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($user);

    expect(AbsenceMotivationResource::getEloquentQuery()->pluck('id'))
        ->toContain($mine->id)
        ->not->toContain($notMine->id);
});
