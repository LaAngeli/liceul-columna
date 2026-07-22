<?php

/**
 * Audiența NOMINALĂ a evenimentelor de calendar (elevi anume): un eveniment poate ținti unul sau mai
 * mulți elevi aleși pe nume, iar reach-ul (elev / părinți / ambii) decide cine, din familia fiecărui
 * elev, îl vede. Vizibilitatea între colegii din personal se restrânge la creator + dirigintele
 * elevului vizat + conducere. Notificările respectă reach-ul. Garda de creare limitează dirigintele
 * la elevii claselor lui. (Cerința beneficiarului 2026-07-22.)
 */

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarScope;
use App\Calendar\Projectors\ManualEventProjector;
use App\Enums\CalendarAudienceReach;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\CatalogNotification;
use App\Observers\CalendarEventObserver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);
});

/** Elev înmatriculat în clasa de test, cu cont propriu + un părinte legat. */
function nominalStudent(mixed $ctx): array
{
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($ctx->class)->for($ctx->year)->create([
        'enrolled_on' => '2025-09-01',
        'left_on' => null,
    ]);

    $studentUser = User::factory()->create();
    $studentUser->assignRole(UserRole::Elev->value);
    $student->update(['user_id' => $studentUser->id]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$student->fresh(), $studentUser, $parent];
}

function projectFor(User $viewer, Student $student): array
{
    $access = new CalendarAccess;
    $scope = new CalendarScope($viewer, collect([$student->load('enrollments')]));

    return (new ManualEventProjector($access))->project(
        $scope,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );
}

it('reach „elev" — elevul îl vede, părintele nu', function () {
    [$student, $studentUser, $parent] = nominalStudent($this);

    $event = CalendarEvent::factory()->forStudents(CalendarAudienceReach::Student)->create(['starts_on' => '2026-06-10']);
    $event->students()->attach($student->id);

    expect(projectFor($studentUser, $student))->toHaveCount(1)
        ->and(projectFor($parent, $student))->toHaveCount(0);
});

it('reach „părinți" — părintele îl vede, elevul nu', function () {
    [$student, $studentUser, $parent] = nominalStudent($this);

    $event = CalendarEvent::factory()->forStudents(CalendarAudienceReach::Guardians)->create(['starts_on' => '2026-06-10']);
    $event->students()->attach($student->id);

    expect(projectFor($parent, $student))->toHaveCount(1)
        ->and(projectFor($studentUser, $student))->toHaveCount(0);
});

it('reach „ambii" — și elevul, și părintele îl văd', function () {
    [$student, $studentUser, $parent] = nominalStudent($this);

    $event = CalendarEvent::factory()->forStudents(CalendarAudienceReach::Both)->create(['starts_on' => '2026-06-10']);
    $event->students()->attach($student->id);

    expect(projectFor($studentUser, $student))->toHaveCount(1)
        ->and(projectFor($parent, $student))->toHaveCount(1);
});

it('un eveniment nominal NU se scurge către familia altui elev', function () {
    [$student, , $parent] = nominalStudent($this);
    [$otherStudent, , $otherParent] = nominalStudent($this);

    $event = CalendarEvent::factory()->forStudents()->create(['starts_on' => '2026-06-10']);
    $event->students()->attach($student->id);

    expect(projectFor($parent, $student))->toHaveCount(1)
        ->and(projectFor($otherParent, $otherStudent))->toHaveCount(0);
});

it('un eveniment nominal nu se rezolvă pe clasă', function () {
    $event = CalendarEvent::factory()->forStudents()->create();

    expect($event->isVisibleToClass($this->class))->toBeFalse();
});

it('conducerea vede orice eveniment nominal; profesorul simplu, doar dacă e implicat', function () {
    [$student] = nominalStudent($this);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    // Profesor fără nicio legătură cu elevul.
    $outsider = User::factory()->create();
    $outsider->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $outsider->id]);

    $event = CalendarEvent::factory()->forStudents()->create(['starts_on' => '2026-06-10', 'created_by' => $director->id]);
    $event->students()->attach($student->id);

    $access = new CalendarAccess;
    $projector = new ManualEventProjector($access);

    $directorItems = $projector->project($access->staffScope($director), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
    $outsiderItems = $projector->project($access->staffScope($outsider), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($directorItems)->toHaveCount(1)
        ->and($outsiderItems)->toHaveCount(0);
});

it('dirigintele elevului vizat vede evenimentul nominal, chiar dacă nu l-a creat', function () {
    [$student] = nominalStudent($this);

    $homeroom = User::factory()->create();
    $homeroom->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $homeroom->id]);
    $this->class->update(['homeroom_teacher_id' => $teacher->id]);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $event = CalendarEvent::factory()->forStudents()->create(['starts_on' => '2026-06-10', 'created_by' => $director->id]);
    $event->students()->attach($student->id);

    $access = new CalendarAccess;
    $items = (new ManualEventProjector($access))->project(
        $access->staffScope($homeroom->fresh()),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($items)->toHaveCount(1);
});

it('audiențele largi rămân vizibile la tot personalul (transparent intern)', function () {
    $outsider = User::factory()->create();
    $outsider->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $outsider->id]);

    CalendarEvent::factory()->forClass($this->class->id)->create(['starts_on' => '2026-06-10']);

    $access = new CalendarAccess;
    $items = (new ManualEventProjector($access))->project(
        $access->staffScope($outsider),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($items)->toHaveCount(1);
});

it('notificarea nominală de creare respectă reach-ul (părinți → tutorele, nu elevul)', function () {
    Notification::fake();

    [$student, $studentUser, $parent] = nominalStudent($this);

    // Fluxul real: eveniment creat → elevi atașați → notificarea nominală declanșată (afterCreate).
    $event = CalendarEvent::factory()->forStudents(CalendarAudienceReach::Guardians)->create(['starts_on' => today()->addWeek()->toDateString()]);
    $event->students()->attach($student->id);
    app(CalendarEventObserver::class)->notifyNominalCreation($event);

    Notification::assertSentTo($parent, CatalogNotification::class);
    Notification::assertNotSentTo($studentUser, CatalogNotification::class);
});

it('observerul `created` NU notifică nominalul (pivotul e gol la acel moment)', function () {
    Notification::fake();

    [$student, , $parent] = nominalStudent($this);

    // `created` rulează înainte de attach — dacă ar notifica, ar trimite unei liste goale (sau greșit).
    $event = CalendarEvent::factory()->forStudents()->make(['starts_on' => today()->addWeek()->toDateString()]);
    $event->save();
    $event->students()->attach($student->id);

    Notification::assertNothingSent();
});
