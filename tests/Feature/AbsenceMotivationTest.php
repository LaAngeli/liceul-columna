<?php

use App\Enums\AudienceDomain;
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
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
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
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('cabinet_flash.motivation_sent'));

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

it('WorkingDays adaugă doar zile lucrătoare (nu cade pe weekend)', function () {
    $start = Carbon::parse('2026-03-02');
    $result = WorkingDays::add($start, 5);

    expect($result->isWeekend())->toBeFalse()
        ->and($result->greaterThan($start))->toBeTrue();

    $count = 0;
    $cursor = $start->copy();
    while ($cursor->lt($result)) {
        $cursor->addDay();
        if (! $cursor->isWeekend()) {
            $count++;
        }
    }
    expect($count)->toBe(5);
});

it('o absență nouă primește termen de motivare = occurred_on + 5 zile lucrătoare', function () {
    $student = Student::factory()->create();
    $absence = Absence::factory()->create([
        'student_id' => $student->id,
        'occurred_on' => '2026-03-02',
        'is_motivated' => false,
    ]);

    $expected = WorkingDays::add(Carbon::parse('2026-03-02'), 5)->toDateString();

    expect($absence->motivation_deadline?->toDateString())->toBe($expected);
});

it('consolidarea blochează absențele cu termen expirat, fără cerere în așteptare', function () {
    $student = Student::factory()->create();
    $absence = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    $absence->update(['motivation_deadline' => Carbon::yesterday()]);

    $this->artisan('app:consolidate-absences')->assertExitCode(0);

    expect($absence->fresh()->motivation_locked_at)->not->toBeNull();
});

it('consolidarea NU blochează dacă o cerere în așteptare acoperă ziua', function () {
    $student = Student::factory()->create();
    $absence = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    $absence->update(['motivation_deadline' => Carbon::yesterday()]);

    AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
    ]);

    $this->artisan('app:consolidate-absences')->assertExitCode(0);

    expect($absence->fresh()->motivation_locked_at)->toBeNull();
});

it('o cerere care acoperă o absență consolidată e marcată EXCEPȚIE', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $absence = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    $absence->update(['motivation_locked_at' => now(), 'motivation_deadline' => Carbon::yesterday()]);

    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/motivare", [
        'reason' => 'Adeverință tardivă',
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-03',
    ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('cabinet_flash.motivation_sent_exception'));

    expect(AbsenceMotivation::query()->where('student_id', $student->id)->where('is_exception', true)->exists())
        ->toBeTrue();
});

it('excepția se validează de vicedirectorul pe educație, nu de diriginte', function () {
    $student = Student::factory()->create();
    $exception = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
        'is_exception' => true,
    ]);

    $educatie = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $educatie->assignRole(UserRole::PrimVicedirector->value);

    $diriginteUser = User::factory()->create();
    $diriginteUser->assignRole(UserRole::Diriginte->value);
    Teacher::factory()->create(['user_id' => $diriginteUser->id]);

    expect($exception->canBeReviewedBy($educatie))->toBeTrue()
        ->and($exception->canBeReviewedBy($diriginteUser))->toBeFalse();
});

it('un director fără domeniul educație NU validează excepția', function () {
    $student = Student::factory()->create();
    $exception = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
        'is_exception' => true,
    ]);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    expect($exception->canBeReviewedBy($director))->toBeFalse();
});
