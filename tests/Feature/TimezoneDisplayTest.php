<?php

/**
 * Fixul SISTEMIC de fus orar (2026-07-21): stocarea rămâne UTC, afișarea e pe ORA ȘCOLII
 * (Europe/Chisinau) — prin FilamentTimezone global (coloane/intrări dateTime + pickere) și
 * prin SchoolCalendar::local() la formatările manuale. Distincția-cheie, pinată aici:
 * INSTANTELE (created_at, reviewed_at…) se convertesc; valorile „CEAS-DE-PERETE" stocate
 * verbatim (messages.scheduled_at — audiențe) NU se convertesc.
 *
 * Instantul de referință: 20.07.2026 22:17 UTC = 21.07.2026 01:17 la Chișinău (+3, vară) —
 * exact decalajul din raportul defect care a pornit tot fixul.
 */

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\Pages\ViewAbsenceMotivation;
use App\Filament\Resources\AdmissionRequests\Pages\ViewAdmissionRequest;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\AdmissionRequest;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\ThreadPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

const TZ_UTC_INSTANT = '2026-07-20 22:17:00';
const TZ_LOCAL_LABEL = '21.07.2026 01:17';

function tzAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

it('fișa motivării arată momentul depunerii pe ora școlii, nu pe UTC', function () {
    $year = AcademicYear::factory()->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for(SchoolClass::factory()->for($year)->create())->for($year)->create();

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);
    AbsenceMotivation::query()->whereKey($motivation->id)->update(['created_at' => TZ_UTC_INSTANT]);

    actingAs(tzAdmin());

    Livewire::test(ViewAbsenceMotivation::class, ['record' => $motivation->id])
        ->assertSee(TZ_LOCAL_LABEL)
        ->assertDontSee('20.07.2026 22:17');
});

it('termenul de validare pornește din ziua LOCALĂ a depunerii, nu din ziua UTC', function () {
    $motivation = AbsenceMotivation::factory()->create(['status' => RequestStatus::Pending]);

    // Depusă luni 20.07 22:17 UTC = MARȚI 21.07, 01:17 ora școlii → termenul = 21.07 + 2 zile
    // lucrătoare = joi 23.07 (pe UTC ar fi fost miercuri 22.07 — o zi mai puțin pentru diriginte).
    AbsenceMotivation::query()->whereKey($motivation->id)->update(['created_at' => TZ_UTC_INSTANT]);

    expect($motivation->fresh()->validationDeadline()?->toDateString())->toBe('2026-07-23');
});

it('infolist-ul Filament (dateTime) convertește automat prin fusul global', function () {
    $request = AdmissionRequest::factory()->visit()->create();
    AdmissionRequest::query()->whereKey($request->id)->update(['created_at' => TZ_UTC_INSTANT]);

    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::AdministratorOperational->value);
    actingAs($staff);

    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->id])
        ->assertSee(TZ_LOCAL_LABEL);
});

it('firul de mesaje convertește instantele, dar audiența PROGRAMATĂ rămâne pe ora tastată', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $root = Message::factory()->create([
        'sender_user_id' => $sender->id,
        'recipient_user_id' => $recipient->id,
        'type' => 'audience',
        'parent_id' => null,
        // Ceas-de-perete: conducerea a fixat audiența la 15:00 (input custom, stocat verbatim).
        'scheduled_at' => '2026-09-10 15:00:00',
    ]);
    Message::query()->whereKey($root->id)->update(['created_at' => TZ_UTC_INSTANT]);

    $thread = app(ThreadPresenter::class)->present($root->fresh(), $recipient->id);

    // Momentul TRIMITERII (instant UTC) → convertit; ora AUDIENȚEI (verbatim) → neatinsă.
    expect($thread['messages'][0]['at'])->toBe(TZ_LOCAL_LABEL)
        ->and($thread['scheduledAt'])->toBe('10.09.2026 15:00');
});

it('inboxul de notificări din cabinet arată ora școlii', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\CatalogNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $parent->id,
        'data' => json_encode(['type' => 'new_grade', 'title' => 'T', 'body' => 'B']),
        'read_at' => null,
        'created_at' => TZ_UTC_INSTANT,
        'updated_at' => TZ_UTC_INSTANT,
    ]);

    actingAs($parent)
        ->get('/cabinet/notificari')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.0.at', TZ_LOCAL_LABEL));
});

it('programarea vizitei face round-trip local→UTC→local prin notificarea de confirmare', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::AdministratorOperational->value);
    actingAs($staff);

    $visit = AdmissionRequest::factory()->visit()->create();

    $component = Livewire::test(ViewAdmissionRequest::class, ['record' => $visit->id])
        ->callAction('scheduleVisit', ['scheduled_visit_at' => '2026-09-10 10:30'])
        ->assertHasNoActionErrors();

    // Tastat 10:30 ora școlii → stocat 07:30 UTC → confirmat înapoi ca 10:30.
    expect($visit->refresh()->scheduled_visit_at?->format('Y-m-d H:i'))->toBe('2026-09-10 07:30');
    $component->assertNotified();

    Livewire::test(ViewAdmissionRequest::class, ['record' => $visit->id])
        ->assertSee('10:30');
});
