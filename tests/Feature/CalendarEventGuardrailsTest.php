<?php

/**
 * Calendarul evenimentelor: AZI e reperul, iar audiența se CONFIRMĂ înainte de salvare.
 *
 * Două defecte de logică semnalate de beneficiar: (1) formularul pornea gol și accepta senin date
 * din trecut — pentru planificare, un eveniment nou în trecut e aproape întotdeauna o greșeală de
 * tastare, nu o intenție; (2) audiența „Treapta 5" nu spunea nimănui cine va vedea evenimentul —
 * „treaptă" înseamnă ciclul (primar/gimnaziu/liceu), nu un an de studiu, iar eticheta nu enumera
 * nici clasele, nici numărul de copii vizați.
 */

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\UserRole;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create(['is_current' => true]);
});

function calendarUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    return $user;
}

it('formularul pornește pe ziua de AZI, în fusul școlii', function () {
    calendarUser(UserRole::AdministratorOperational);

    Livewire::test(CreateCalendarEvent::class)
        ->assertSchemaStateSet([
            'starts_on' => SchoolCalendar::localNow()->toDateString(),
        ]);
});

it('data din trecut e refuzată pe SERVER pentru operațional, dar deschisă directorului', function () {
    calendarUser(UserRole::AdministratorOperational);

    $yesterday = SchoolCalendar::localNow()->subDay()->toDateString();

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => CalendarEventScope::Global->value,
            'title' => 'Eveniment antedatat',
            'starts_on' => $yesterday,
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_on']);

    expect(CalendarEvent::query()->where('title', 'Eveniment antedatat')->exists())->toBeFalse();

    // Consemnarea retrospectivă e un act de autoritate — directorul o poate face.
    calendarUser(UserRole::Director);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => CalendarEventScope::Global->value,
            'title' => 'Consemnare retrospectivă',
            'starts_on' => $yesterday,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CalendarEvent::query()->where('title', 'Consemnare retrospectivă')->exists())->toBeTrue();
});

it('ora deja trecută a zilei de azi e refuzată; una viitoare trece', function () {
    calendarUser(UserRole::AdministratorOperational);

    $now = SchoolCalendar::localNow();

    // Testul are nevoie de o oră „trecută" și una „viitoare" în ACEEAȘI zi — la miezul nopții
    // n-ar exista ora trecută, deci ancorăm momentul la prânz, în fusul școlii.
    if ($now->hour < 2 || $now->hour > 21) {
        Carbon::setTestNow($now->setTime(12, 0));
        $now = SchoolCalendar::localNow();
    }

    $base = [
        'type' => CalendarEventType::Meeting->value,
        'visibility_scope' => CalendarEventScope::Global->value,
        'starts_on' => $now->toDateString(),
    ];

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm($base + ['title' => 'Ora trecută', 'start_time' => $now->copy()->subHour()->format('H:i')])
        ->call('create')
        ->assertHasFormErrors(['start_time']);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm($base + ['title' => 'Ora viitoare', 'start_time' => $now->copy()->addHour()->format('H:i')])
        ->call('create')
        ->assertHasNoFormErrors();

    Carbon::setTestNow();
});

it('un eveniment din trecut rămâne editabil fără a fi mutat — doar mutarea în altă zi trecută e refuzată', function () {
    calendarUser(UserRole::AdministratorOperational);

    // Istoric legitim, creat pe altă cale decât formularul.
    $event = CalendarEvent::factory()->create([
        'title' => 'Eveniment consumat',
        'starts_on' => SchoolCalendar::localNow()->subDays(10)->toDateString(),
        'visibility_scope' => CalendarEventScope::Global,
    ]);

    // Corectarea titlului, cu data NESCHIMBATĂ → trece.
    Livewire::test(EditCalendarEvent::class, ['record' => $event->getKey()])
        ->fillForm(['title' => 'Eveniment consumat (titlu corectat)'])
        ->call('save')
        ->assertHasNoFormErrors();

    // Mutarea în ALTĂ zi din trecut → refuzată operaționalului.
    Livewire::test(EditCalendarEvent::class, ['record' => $event->getKey()])
        ->fillForm(['starts_on' => SchoolCalendar::localNow()->subDays(3)->toDateString()])
        ->call('save')
        ->assertHasFormErrors(['starts_on']);
});

it('rezumatul audienței spune CINE vede: clasele reale și numărul de elevi', function () {
    calendarUser(UserRole::AdministratorOperational);

    $va = SchoolClass::factory()->for($this->year)->create(['name' => 'V', 'section' => '1', 'grade_level' => 5]);
    $vb = SchoolClass::factory()->for($this->year)->create(['name' => 'V', 'section' => '2', 'grade_level' => 5]);

    foreach ([[$va, 3], [$vb, 2]] as [$class, $count]) {
        Student::factory()->count($count)->create()->each(
            fn (Student $student) => Enrollment::factory()->for($student)->for($class)->for($this->year)->create(),
        );
    }

    // Anul de studiu: ambele clase paralele + suma elevilor.
    $grade = CalendarEventForm::audienceSummary(CalendarEventScope::GradeLevel->value, 5, null);

    expect($grade)->toContain('V 1', 'V 2')
        ->and($grade)->toContain('5');

    // O clasă: doar ea și doar elevii ei.
    $single = CalendarEventForm::audienceSummary(CalendarEventScope::SchoolClass->value, null, $va->id);

    expect($single)->toContain('V 1')
        ->and($single)->toContain('3')
        ->and($single)->not->toContain('V 2');

    // Fără selecție încă → îndrumare, nu o afirmație falsă despre audiență.
    expect(CalendarEventForm::audienceSummary(CalendarEventScope::GradeLevel->value, null, null))
        ->toBe(__('panel.forms.calendar_event.summary_pick_grade'));
});

it('opțiunile pe an de studiu enumeră clasele, nu mai afișează „Treapta N"', function () {
    calendarUser(UserRole::AdministratorOperational);

    SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => '1', 'grade_level' => 7]);
    SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => '2', 'grade_level' => 7]);

    $html = Livewire::test(CreateCalendarEvent::class)
        ->fillForm(['visibility_scope' => CalendarEventScope::GradeLevel->value])
        ->html();

    expect($html)->toContain('VII 1, VII 2')
        ->and($html)->not->toContain('Treapta');
});
