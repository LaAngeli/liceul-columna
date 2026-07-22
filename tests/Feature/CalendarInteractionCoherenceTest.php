<?php

/**
 * Cluster Calendar/Evenimente — coerența interacțiunilor (mapare + verificare adversarială):
 *  - widget-ul „Evenimente apropiate" respectă excluderea AT (decizia „AT = doar agregate") și
 *    arată evenimentele multi-zi ÎN DESFĂȘURARE (ca pagina Calendar);
 *  - absența devenită NEMOTIVATĂ prin mutarea datei primește termen de motivare (înainte: NULL
 *    pe veci → invizibilă în calendar, sărită de consolidare);
 *  - excepțiile (motivări tardive) notifică vicedirectorul pe educație — aprobatorul real;
 *  - schimbarea zilelor libere RECALCULEAZĂ termenele de motivare încă deschise (snapshot);
 *  - ziua-limită a dirigintelui e inclusivă (aceeași convenție ca a familiei);
 *  - zilele libere au policy (nu doar gating de resursă).
 */

use App\Enums\AudienceDomain;
use App\Enums\CalendarEventScope;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\EditAbsence;
use App\Filament\Widgets\UpcomingEvents;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use App\Support\Holidays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    Holidays::flush();

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
});

afterEach(function () {
    Carbon::setTestNow();
    Holidays::flush();
});

// ─── Widget „Evenimente apropiate" ───────────────────────────────────────────────────────

it('widget-ul Evenimente apropiate e ascuns administratorului tehnic (AT = doar agregate)', function () {
    $at = User::factory()->create();
    $at->assignRole(UserRole::AdministratorTehnic->value);
    actingAs($at);
    expect(UpcomingEvents::canView())->toBeFalse();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
    expect(UpcomingEvents::canView())->toBeTrue();
});

it('widget-ul arată și evenimentele multi-zi aflate ÎN DESFĂȘURARE', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    // Început ieri, se termină mâine — pagina Calendar îl arată azi; widget-ul îl omitea.
    CalendarEvent::factory()->create([
        'title' => 'Săptămâna verde',
        'starts_on' => today()->subDay(),
        'ends_on' => today()->addDay(),
    ]);

    Livewire::test(UpcomingEvents::class)
        ->assertViewHas('events', fn (array $events): bool => collect($events)->contains(
            fn (array $e): bool => $e['title'] === 'Săptămâna verde',
        ));
});

// ─── Absența mutată în afara dovezii primește termen ─────────────────────────────────────

it('absența devenită nemotivată prin mutarea datei primește termen de motivare', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    // Disciplina trebuie PREDATĂ în clasă (selectul din formular e în cascadă clasă→disciplină).
    $subject = Subject::factory()->create();
    $teacher = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $subject->id,
    ]);

    $absence = Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
        'term_id' => $this->term->id, 'teacher_id' => $teacher->id,
        'occurred_on' => '2026-03-10', 'is_motivated' => true, 'motivation_deadline' => null,
    ]);
    AbsenceMotivation::factory()->create([
        'student_id' => $student->id, 'status' => RequestStatus::Approved,
        'period_start' => '2026-03-10', 'period_end' => '2026-03-10',
    ]);

    // Mutată pe 9 martie — în afara perioadei acoperite de dovadă.
    Livewire::test(EditAbsence::class, ['record' => $absence->id])
        ->fillForm(['occurred_on' => '2026-03-09'])
        ->call('save')
        ->assertHasNoFormErrors();

    $absence->refresh();

    // Nemotivată + termen = 9 martie (luni) + 5 zile lucrătoare = 16 martie (luni).
    expect($absence->is_motivated)->toBeFalse()
        ->and($absence->motivation_deadline)->not->toBeNull()
        ->and($absence->motivation_deadline->toDateString())->toBe('2026-03-16');
});

// ─── Excepțiile notifică aprobatorul real ────────────────────────────────────────────────

it('motivarea-EXCEPȚIE notifică vicedirectorul pe educație, nu dirigintele', function () {
    $homeroomUser = User::factory()->create();
    $homeroom = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    $educatie = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $educatie->assignRole(UserRole::PrimVicedirector->value);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    Notification::fake();

    AbsenceMotivation::factory()->create([
        'student_id' => $student->id, 'status' => RequestStatus::Pending, 'is_exception' => true,
    ]);

    Notification::assertSentTo(
        $educatie,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::AbsenceMotivationSubmitted,
    );
    // Dirigintele nu poate aproba excepția → nu mai primește ping fără acțiune.
    Notification::assertNothingSentTo($homeroomUser);

    // Cererea NORMALĂ merge în continuare la diriginte.
    AbsenceMotivation::factory()->create([
        'student_id' => $student->id, 'status' => RequestStatus::Pending, 'is_exception' => false,
    ]);

    Notification::assertSentTo(
        $homeroomUser,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::AbsenceMotivationSubmitted,
    );
});

// ─── Zilele libere recalculează termenele deschise ───────────────────────────────────────

it('adăugarea unei vacanțe recalculează termenele de motivare încă deschise', function () {
    $student = Student::factory()->create();

    // Absență luni 2 martie → termen = +5 zile lucrătoare = luni 9 martie (fără vacanțe).
    $absence = Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $this->class->id,
        'subject_id' => Subject::factory()->create()->id,
        'term_id' => $this->term->id,
        'occurred_on' => '2026-03-02', 'is_motivated' => false,
        'motivation_deadline' => '2026-03-09',
    ]);

    // Absență deja CONSOLIDATĂ (locked) — termen istoric, nu se atinge.
    $locked = Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $this->class->id,
        'subject_id' => Subject::factory()->create()->id,
        'term_id' => $this->term->id,
        'occurred_on' => '2026-03-02', 'is_motivated' => false,
        'motivation_deadline' => '2026-03-09', 'motivation_locked_at' => now(),
    ]);

    // AO adaugă o vacanță miercuri–joi (4–5 martie) → termenul deschis sare 2 zile.
    Holiday::create(['name' => 'Vacanță test', 'starts_on' => '2026-03-04', 'ends_on' => '2026-03-05']);

    expect($absence->refresh()->motivation_deadline->toDateString())->toBe('2026-03-11')
        ->and($locked->refresh()->motivation_deadline->toDateString())->toBe('2026-03-09');
});

// ─── Ziua-limită a dirigintelui e inclusivă ──────────────────────────────────────────────

it('dirigintele NU e restant în ziua-limită, ci abia din ziua următoare', function () {
    // Depusă luni → termen de validare = +2 zile lucrătoare = miercuri.
    Carbon::setTestNow('2026-03-02 09:00');
    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'status' => RequestStatus::Pending,
    ]);

    // Miercuri la prânz — ziua-limită e INCLUSIVĂ, încă nu e restant.
    Carbon::setTestNow('2026-03-04 12:00');
    expect($motivation->isOverdue())->toBeFalse();

    // Joi dimineață — restant.
    Carbon::setTestNow('2026-03-05 08:00');
    expect($motivation->isOverdue())->toBeTrue();
});

// ─── Evenimentele noi/anulate notifică familiile din scope (decizie user, 2026-07-12) ────

it('crearea unui eveniment de clasă notifică DOAR familiile clasei; anularea la fel', function () {
    $inScope = User::factory()->create();
    $inScope->assignRole(UserRole::Elev->value);
    $studentIn = Student::factory()->create(['user_id' => $inScope->id]);
    Enrollment::factory()->for($studentIn)->for($this->class)->for($this->year)->create();

    $otherClass = SchoolClass::factory()->for($this->year)->create();
    $outOfScope = User::factory()->create();
    $outOfScope->assignRole(UserRole::Elev->value);
    $studentOut = Student::factory()->create(['user_id' => $outOfScope->id]);
    Enrollment::factory()->for($studentOut)->for($otherClass)->for($this->year)->create();

    Notification::fake();

    $event = CalendarEvent::factory()->create([
        'title' => 'Ședință cu părinții',
        'visibility_scope' => CalendarEventScope::SchoolClass,
        'school_class_id' => $this->class->id,
        'starts_on' => today()->addWeek(),
    ]);

    Notification::assertSentTo(
        $inScope,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewCalendarEvent,
    );
    Notification::assertNothingSentTo($outOfScope);

    // Anularea (soft-delete) anunță aceeași audiență.
    $event->delete();

    Notification::assertSentTo(
        $inScope,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::CalendarEventCancelled,
    );
    Notification::assertNothingSentTo($outOfScope);
});

it('evenimentele TRECUTE nu notifică pe nimeni (bookkeeping, nu anunț)', function () {
    $family = User::factory()->create();
    $family->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $family->id]);
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    Notification::fake();

    CalendarEvent::factory()->create([
        'visibility_scope' => CalendarEventScope::Global,
        'starts_on' => today()->subMonth(),
        'ends_on' => today()->subMonth(),
    ]);

    Notification::assertNothingSentTo($family);
});

it('comutatorul „Anunță familiile" OPRIT tace și la creare, și la anulare', function () {
    $family = User::factory()->create();
    $family->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $family->id]);
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    Notification::fake();

    // Eveniment viitor, în scope-ul familiei, dar creat FĂRĂ notificare → doar apare în calendar.
    $event = CalendarEvent::factory()->silent()->create([
        'visibility_scope' => CalendarEventScope::SchoolClass,
        'school_class_id' => $this->class->id,
        'starts_on' => today()->addWeek(),
    ]);

    Notification::assertNothingSentTo($family);

    // Anularea unui eveniment tăcut rămâne tăcută.
    $event->delete();

    Notification::assertNothingSentTo($family);
});

// ─── Zilele libere au policy ─────────────────────────────────────────────────────────────

it('zilele libere se scriu doar de configuratorii de orare (policy, nu doar resursă)', function () {
    $holiday = Holiday::create(['name' => 'Ziua școlii', 'starts_on' => '2026-05-01']);

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    expect(Gate::forUser($ao)->check('update', $holiday))->toBeTrue()
        ->and(Gate::forUser($profesor)->check('update', $holiday))->toBeFalse()
        ->and(Gate::forUser($profesor)->check('create', Holiday::class))->toBeFalse();
});
