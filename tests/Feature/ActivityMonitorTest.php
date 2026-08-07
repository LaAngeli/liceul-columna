<?php

/**
 * „PULSUL ACTIVITĂȚII" (redesenat 07.08.2026) — calendarul de intensitate al muncii personale.
 *
 * Testele fixează contractul lui pulse(): scoping-ul pe utilizator (acțiunile LUI, nu ale
 * colegilor), bucketarea pe ZIUA din fusul ȘCOLII (stocarea e UTC), chips-urile doar pentru
 * categoriile cu activitate, toggle-ul care stinge o categorie din totaluri și ferestrele de
 * perioadă cu whitelist.
 */

use App\Enums\UserRole;
use App\Filament\Widgets\ActivityMonitor;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // ANCORĂ temporală (miercuri): ferestrele pulsului depind de ziua curentă — fără ancoră,
    // numărătorile ar deriva la granițele de săptămână/lună.
    Carbon::setTestNow(
        Carbon::parse('2026-04-22 12:00', SchoolCalendar::TIMEZONE),
    );
});

afterEach(fn () => Carbon::setTestNow());

function activityStaffUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** Profesor legat de o fișă, repartizat la clasele date. */
function activityTeacherUser(SchoolClass ...$classes): User
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

/**
 * Pulsul unui widget proaspăt, cu perioada / categoriile stinse date.
 *
 * @param  list<string>  $off
 * @return array<string, mixed>
 */
function activityPulse(string $period = '12w', array $off = []): array
{
    $widget = new ActivityMonitor;
    $widget->period = $period;
    $widget->off = $off;

    return $widget->pulse();
}

/** Numărătoarea unui chip de categorie (după cheie); -1 = chip-ul lipsește. */
function activityCatCount(array $pulse, string $key): int
{
    foreach ($pulse['cats'] as $cat) {
        if ($cat['key'] === $key) {
            return (int) $cat['count'];
        }
    }

    return -1;
}

it('e o secțiune standard: vizibil oricărui staff logat, ascuns musafirului', function () {
    $this->actingAs(activityStaffUser(UserRole::Director));
    expect(ActivityMonitor::canView())->toBeTrue();

    $this->actingAs(activityStaffUser(UserRole::AdministratorTehnic));
    expect(ActivityMonitor::canView())->toBeTrue();

    $this->actingAs(activityTeacherUser());
    expect(ActivityMonitor::canView())->toBeTrue();

    auth('web')->logout();
    expect(ActivityMonitor::canView())->toBeFalse();
});

it('scopează notele pe profesorul curent (exclude ale altuia + anulate)', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();

    $teacherUser = activityTeacherUser($classA);
    $teacherId = $teacherUser->teacher->id;

    Grade::factory()->create(['teacher_id' => $teacherId]);                         // a lui — contează
    Grade::factory()->create(['teacher_id' => $teacherId, 'annulled_at' => now()]); // anulată — NU
    Grade::factory()->create();                                                     // a altui profesor — NU

    $this->actingAs($teacherUser);
    $pulse = activityPulse();

    expect(activityCatCount($pulse, 'grades'))->toBe(1)
        ->and($pulse['kpi']['total'])->toBe(1);
});

it('scopează absențele pe cele CONSEMNATE de profesor (teacher_id, nu toată clasa)', function () {
    $teacherUser = activityTeacherUser();
    $teacherId = $teacherUser->teacher->id;

    Absence::factory()->create(['teacher_id' => $teacherId]);
    Absence::factory()->create(); // a altui profesor — NU (altfel ar prinde importul + colegii)

    $this->actingAs($teacherUser);

    expect(activityCatCount(activityPulse(), 'absences'))->toBe(1);
});

it('scopează corecțiile pe cererile + revizuirile userului, mesajele pe expeditor', function () {
    $userA = activityStaffUser(UserRole::Director);
    $userB = activityStaffUser(UserRole::Profesor);

    GradeCorrection::factory()->create(['requested_by_user_id' => $userA->id]);
    GradeCorrection::factory()->create(['reviewed_by_user_id' => $userA->id, 'reviewed_at' => now()]);
    GradeCorrection::factory()->create(['requested_by_user_id' => $userB->id]); // al altuia — NU
    Message::factory()->create(['sender_user_id' => $userA->id]);
    Message::factory()->create(['sender_user_id' => $userB->id]);               // al altuia — NU

    $this->actingAs($userA);
    $pulse = activityPulse();

    expect(activityCatCount($pulse, 'corrections'))->toBe(2)
        ->and(activityCatCount($pulse, 'messages'))->toBe(1);
});

it('chip-ul apare DOAR pentru categoriile cu activitate; fără nimic → empty', function () {
    $teacherUser = activityTeacherUser();
    Grade::factory()->create(['teacher_id' => $teacherUser->teacher->id]);

    $this->actingAs($teacherUser);
    $pulse = activityPulse();

    // Doar „Note" are chip — absențele/corecțiile/mesajele la zero nu fac zgomot.
    expect(array_column($pulse['cats'], 'key'))->toBe(['grades'])
        ->and($pulse['empty'])->toBeFalse();

    // Un cont fără nicio acțiune → starea goală, prietenoasă.
    $this->actingAs(activityStaffUser(UserRole::AdministratorTehnic));

    expect(activityPulse()['empty'])->toBeTrue();
});

it('stingerea unei categorii o scoate din totaluri și din intensitate, dar chip-ul rămâne', function () {
    $teacherUser = activityTeacherUser();
    $teacherId = $teacherUser->teacher->id;

    Grade::factory()->count(2)->create(['teacher_id' => $teacherId]);
    Absence::factory()->create(['teacher_id' => $teacherId]);

    $this->actingAs($teacherUser);

    $all = activityPulse();
    expect($all['kpi']['total'])->toBe(3)->and($all['kpi']['today'])->toBe(3);

    $absOff = activityPulse('12w', ['absences']);

    expect($absOff['kpi']['total'])->toBe(2)
        // Chip-ul rămâne vizibil (cu numărătoarea lui), doar marcat stins.
        ->and(activityCatCount($absOff, 'absences'))->toBe(1)
        ->and(collect($absOff['cats'])->firstWhere('key', 'absences')['active'])->toBeFalse();
});

it('ziua se judecă în fusul ȘCOLII: o acțiune la 22:30 UTC aparține zilei următoare', function () {
    $teacherUser = activityTeacherUser();

    // 22:30 UTC pe 21.04 = 01:30 pe 22.04 în fusul școlii (UTC+3 vara).
    Grade::factory()->create([
        'teacher_id' => $teacherUser->teacher->id,
        'created_at' => Carbon::parse('2026-04-21 22:30', 'UTC'),
    ]);

    $this->actingAs($teacherUser);
    $pulse = activityPulse('4w');

    // Ancora e 22.04 → acțiunea de la 01:30 local e „azi", nu „ieri".
    expect($pulse['kpi']['today'])->toBe(1);

    $bars = collect($pulse['bars'])->keyBy('iso');

    expect($bars['2026-04-22']['total'])->toBe(1)
        ->and($bars['2026-04-21']['total'])->toBe(0);
});

it('granularitatea urmează fereastra: 4w = bare zilnice, 12w = săptămânale; străin → 12w', function () {
    $this->actingAs(activityTeacherUser());

    $scurt = activityPulse('4w');
    $lung = activityPulse('12w');

    // 4 săptămâni × 7 = 28 de bare zilnice; 12 săptămâni = 12 bare săptămânale.
    expect($scurt['granularity'])->toBe('day')
        ->and(count($scurt['bars']))->toBe(28)
        ->and($lung['granularity'])->toBe('week')
        ->and(count($lung['bars']))->toBe(12)
        ->and(count(activityPulse('bogus')['bars']))->toBe(12);

    $bars = collect($scurt['bars']);

    // Exact o bară „azi"; joia-duminica săptămânii curente sunt viitor (ancora e miercuri).
    expect($bars->where('today', true))->toHaveCount(1)
        ->and($bars->where('future', true))->toHaveCount(4)
        // Weekendurile sunt marcate (8 zile de S+D în 4 săptămâni) — eticheta lor e estompată.
        ->and($bars->where('weekend', true))->toHaveCount(8);

    // Și pe săptămâni, exact o bară poartă „azi" (cea a săptămânii curente).
    expect(collect($lung['bars'])->where('today', true))->toHaveCount(1);
});

it('înălțimile segmentelor sunt relative la vârful ferestrei, iar vârful intră în KPI', function () {
    $teacherUser = activityTeacherUser();
    $teacherId = $teacherUser->teacher->id;

    // Luni: 8 note (vârf); marți: 2; miercuri (azi): 1.
    Grade::factory()->count(8)->create(['teacher_id' => $teacherId, 'created_at' => Carbon::parse('2026-04-20 09:00', SchoolCalendar::TIMEZONE)]);
    Grade::factory()->count(2)->create(['teacher_id' => $teacherId, 'created_at' => Carbon::parse('2026-04-21 09:00', SchoolCalendar::TIMEZONE)]);
    Grade::factory()->create(['teacher_id' => $teacherId, 'created_at' => Carbon::parse('2026-04-22 09:00', SchoolCalendar::TIMEZONE)]);

    $this->actingAs($teacherUser);
    $pulse = activityPulse('4w');
    $bars = collect($pulse['bars'])->keyBy('iso');

    expect($bars['2026-04-20']['segments'][0]['height'])->toBe(100.0) // vârful = bară plină
        ->and($bars['2026-04-21']['segments'][0]['height'])->toBe(25.0)
        ->and($bars['2026-04-19']['segments'])->toBe([])              // zi fără nimic = ciot
        ->and($pulse['kpi']['peak'])->toMatchArray(['count' => 8])
        ->and($pulse['kpi']['week'])->toBe(11);

    // Tooltip-ul barei poartă defalcarea — acolo se citesc acțiunile exacte.
    expect($bars['2026-04-20']['title'])->toContain('8')->toContain('note');
});

it('widget-ul se randează cu titlul nou și pastilele de perioadă; toggle-urile au whitelist', function () {
    $teacherUser = activityTeacherUser();
    Grade::factory()->create(['teacher_id' => $teacherUser->teacher->id]);

    $this->actingAs($teacherUser);

    Livewire::test(ActivityMonitor::class)
        ->assertOk()
        ->assertSee('Monitor activitate')
        ->assertSee('Ultimele 12 săptămâni')
        ->call('setPeriod', 'orice-altceva')
        ->assertSet('period', '12w')
        ->call('setPeriod', '4w')
        ->assertSet('period', '4w')
        ->call('toggleCategory', 'grades')
        ->assertSet('off', ['grades'])
        ->call('toggleCategory', 'grades')
        ->assertSet('off', [])
        ->call('toggleCategory', 'nu-exista')
        ->assertSet('off', []);
});
