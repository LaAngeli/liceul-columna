<?php

use App\Enums\AudienceDomain;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\AbsenceMotivations\Pages\ViewAbsenceMotivation;
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
use Livewire\Livewire;
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
    // O absență nemotivată recentă, cu termenul de depunere ÎNCĂ deschis → motivare NORMALĂ
    // (nu excepție). Cererea trebuie să vizeze absențe reale (#37).
    Absence::factory()->create([
        'student_id' => $student->id,
        'occurred_on' => Carbon::yesterday()->toDateString(),
        'is_motivated' => false,
        'motivation_deadline' => Carbon::tomorrow()->toDateString(),
    ]);

    $this->actingAs($parent)
        ->post("/cabinet/elev/{$student->id}/motivare", [
            'reason' => 'Gripă, recomandare medicală',
            'period_start' => Carbon::yesterday()->toDateString(),
            'period_end' => Carbon::yesterday()->toDateString(),
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('cabinet_flash.motivation_sent'));

    expect(AbsenceMotivation::query()
        ->where('student_id', $student->id)
        ->where('status', RequestStatus::Pending)
        ->count())->toBe(1);
});

// ─── Robustețea depunerii motivărilor (#37) ──────────────────────────────────────────────

it('motivarea pe o perioadă FĂRĂ absențe nemotivate e respinsă (aprobare oarbă)', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);
    // NICIO absență în perioadă → cererea nu are ce motiva.

    $this->actingAs($parent)
        ->post("/cabinet/elev/{$student->id}/motivare", [
            'reason' => 'Am greșit luna.',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-03',
        ])
        ->assertSessionHasErrors(['period_start']);

    expect(AbsenceMotivation::query()->count())->toBe(0);
});

it('a doua motivare cu perioadă suprapusă (pending) e respinsă (anti-duplicat)', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);

    $submit = fn () => $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/motivare", [
        'reason' => 'Boală.',
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
    ]);

    $submit()->assertRedirect();
    // A doua cerere pe interval suprapus, cât prima e pending → respinsă.
    $submit()->assertSessionHasErrors(['period_start']);

    expect(AbsenceMotivation::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('absența consemnată DUPĂ aprobarea motivării care o acoperă e motivată din start (simetric cu editarea)', function () {
    $student = Student::factory()->create();

    // Motivare deja APROBATĂ pentru 01–05 martie.
    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Approved,
    ]);

    // Absență introdusă retroactiv pe 03 martie (în perioadă) → motivată automat.
    $absence = Absence::factory()->create([
        'student_id' => $student->id,
        'occurred_on' => '2026-03-03',
        'is_motivated' => false,
    ]);

    expect($absence->fresh()->is_motivated)->toBeTrue()
        ->and($absence->fresh()->motivation_deadline)->toBeNull();
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

// ─── Fișa cererii (analiza completă înainte de verdict) ─────────────────────────────────

/**
 * Elev înmatriculat într-o clasă cu diriginte: [$student, $diriginteUser, $class].
 *
 * @return array{0: Student, 1: User, 2: SchoolClass}
 */
function amvContext(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $diriginte = User::factory()->create();
    $diriginte->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $diriginte->id]);
    $class->update(['homeroom_teacher_id' => $teacher->id]);

    return [$student, $diriginte, $class];
}

it('fișa arată perioada, impactul pe absențe, motivul integral și contextul elevului', function () {
    [$student, $diriginte] = amvContext();
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-03', 'is_motivated' => true]);
    // În afara perioadei — nu apare în impact.
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-20', 'is_motivated' => false]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'reason' => 'Motiv lung pe care lista îl trunchia — fișa îl arată integral, fără puncte de suspensie.',
        'status' => RequestStatus::Pending,
    ]);

    $this->actingAs($diriginte);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->assertSee(__('panel.absence_motivation_view.absences_in_period'))
        ->assertSee('Motiv lung pe care lista îl trunchia — fișa îl arată integral, fără puncte de suspensie.')
        // Doar absența NEMOTIVATĂ din perioadă intră în efectul validării.
        ->assertSee(trans_choice('panel.absence_motivation_view.will_motivate', 1, ['count' => 1]))
        ->assertSee('02.03.2026')
        ->assertSee($student->full_name)
        ->assertSee(__('panel.actions.validate.label'))
        ->assertSee(__('panel.actions.reject.label'));
});

it('validarea din fișă marchează absențele din perioadă; judecata dispare după verdict', function () {
    [$student, $diriginte] = amvContext();
    $inPeriod = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);
    $outOfPeriod = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-10', 'is_motivated' => false]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
    ]);

    $this->actingAs($diriginte);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->callAction('approve', ['review_note' => 'Adeverință valabilă.'])
        ->assertNotified();

    expect($inPeriod->fresh()->is_motivated)->toBeTrue()
        ->and($outOfPeriod->fresh()->is_motivated)->toBeFalse()
        ->and($motivation->fresh()->status)->toBe(RequestStatus::Approved);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});

it('respingerea din fișă CERE motiv; cu motiv → consemnată, absențele rămân neatinse', function () {
    [$student, $diriginte] = amvContext();
    $absence = Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-02', 'is_motivated' => false]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
    ]);

    $this->actingAs($diriginte);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->callAction('reject', ['review_note' => ''])
        ->assertHasActionErrors();

    expect($motivation->refresh()->status)->toBe(RequestStatus::Pending);

    // Instanță separată: după validarea eșuată, același component nu mai re-montează acțiunea.
    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->callAction('reject', ['review_note' => 'Fără justificativ medical.'])
        ->assertNotified();

    expect($motivation->refresh()->status)->toBe(RequestStatus::Rejected)
        ->and($motivation->review_note)->toBe('Fără justificativ medical.')
        ->and($absence->fresh()->is_motivated)->toBeFalse();
});

it('excepția pe fișă: dirigintele o vede FĂRĂ judecată (cu explicație); vicedirectorul pe educație CU', function () {
    [$student, $diriginte] = amvContext();

    $exception = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
        'is_exception' => true,
    ]);

    $this->actingAs($diriginte);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $exception->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject')
        ->assertSee(__('panel.absence_motivation_view.exception_only_hint'));

    $educatie = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $educatie->assignRole(UserRole::PrimVicedirector->value);
    $this->actingAs($educatie);

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $exception->id])
        ->assertActionVisible('approve')
        ->assertActionVisible('reject');
});

it('fișa cererii din altă clasă nu există pentru alt diriginte (404)', function () {
    [$student, $diriginte] = amvContext();
    [, $altDiriginte] = amvContext();

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    // Scoping-ul ascunde cererile altor clase → 404 (nu confirmă nici existența).
    $this->actingAs($altDiriginte)
        ->get("/admin/absence-motivations/{$motivation->id}")
        ->assertNotFound();

    $this->actingAs($diriginte)
        ->get("/admin/absence-motivations/{$motivation->id}")
        ->assertOk();
});

it('verdictul notifică familia; dirigintele află DOAR când a decis altcineva', function () {
    [$student, $diriginte] = amvContext();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Excepție judecată de vicedirectorul pe educație → familia + dirigintele află.
    $exception = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-05',
        'status' => RequestStatus::Pending,
        'is_exception' => true,
    ]);

    $educatie = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $educatie->assignRole(UserRole::PrimVicedirector->value);

    $exception->approve($educatie->id, 'Adeverință acceptată.');

    $decided = fn (User $user): int => $user->notifications()
        ->where('data->type', NotificationType::AbsenceMotivationDecided->value)
        ->count();

    expect($decided($parent))->toBe(1)
        ->and($decided($diriginte))->toBe(1);

    // Cerere normală judecată chiar de diriginte → familia află, el NU se auto-anunță.
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-04-02', 'is_motivated' => false]);
    $normal = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-03',
        'status' => RequestStatus::Pending,
    ]);

    $normal->reject($diriginte->id, 'Perioada nu corespunde adeverinței.');

    expect($decided($parent))->toBe(2)
        ->and($decided($diriginte))->toBe(1);
});

it('familia vede în cabinet nota validatorului (motivul respingerii)', function () {
    [$student] = amvContext();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);
    $motivation->reject(User::factory()->create()->id, 'Adeverința nu acoperă perioada.');

    // „motivations" e prop DEFER — se cere explicit, ca în reload-ul parțial Inertia.
    $response = $this->actingAs($parent)->get(
        "/cabinet/elev/{$student->id}",
        inertiaPartialHeaders('cabinet/student-profile', 'motivations'),
    );

    $response->assertOk();
    expect($response->json('props.motivations.0.note'))->toBe('Adeverința nu acoperă perioada.');
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
