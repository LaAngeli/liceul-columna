<?php

/**
 * Fluxul nou al absenței (cerința beneficiarului, 04.08.2026): profesorul doar CONSEMNEAZĂ
 * („Absent", fără statut — el rareori știe de ce lipsește elevul), iar DIRIGINTELE fixează
 * statutul (motivată/nemotivată) pe parcursul zilei, din secțiunea Absențe.
 *
 * Testele apără exact granițele care ar putrezi tăcut: profesorul nu poate strecura un statut
 * (nici prin payload), nu mai editează și nu mai șterge nimic; dirigintele decide DOAR pentru
 * clasa lui; consolidarea automată închide absențele nedecise ca nemotivate; iar aprobarea unei
 * motivări acoperă și absențele încă fără statut.
 */

use App\Enums\AbsenceStatus;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-10', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'A']);
    $this->subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);

    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    // Profesorul disciplinei (NU diriginte).
    $this->profUser = User::factory()->create();
    $this->profUser->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->profUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // Dirigintele clasei.
    $this->homeroomUser = User::factory()->create();
    $this->homeroomUser->assignRole(UserRole::Diriginte->value);
    $this->homeroom = Teacher::factory()->create(['user_id' => $this->homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $this->homeroom->id]);
});

/** O absență a clasei de test, în semestrul curent. */
function pendingAbsence(array $overrides = []): Absence
{
    return Absence::factory()->pending()->create([
        'student_id' => test()->student->id,
        'subject_id' => test()->subject->id,
        'school_class_id' => test()->class->id,
        'term_id' => test()->term->id,
        'teacher_id' => test()->teacher->id,
        'occurred_on' => '2026-03-10',
        ...$overrides,
    ]);
}

// ─── Consemnarea profesorului: fără statut, orice ar trimite ─────────────────────────────

it('profesorul consemnează și absența pleacă FĂRĂ statut, chiar dacă payload-ul strecoară unul', function () {
    actingAs($this->profUser);

    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'occurred_on' => '2026-03-10',
            // Câmpul e invizibil profesorului, dar garda e pe SERVER, nu în vizibilitate.
            'status' => AbsenceStatus::Motivated->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $absence = Absence::query()->sole();

    expect($absence->is_motivated)->toBeNull()
        ->and($absence->status())->toBe(AbsenceStatus::Pending)
        // Fereastra familiei curge de la data absenței, nu de la decizia dirigintelui.
        ->and($absence->motivation_deadline?->toDateString())->toBe(WorkingDays::add(Carbon::parse('2026-03-10'), 5)->toDateString());
});

it('dirigintele poate consemna direct cu statut, din același formular', function () {
    actingAs($this->homeroomUser);

    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'occurred_on' => '2026-03-10',
            'status' => AbsenceStatus::Unmotivated->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absence::query()->sole()->is_motivated)->toBeFalse();
});

// ─── Politica: profesorul doar vede; dirigintele decide DOAR la clasa lui ────────────────

it('profesorul nu mai editează și nu mai șterge absențe — nici pe ale lui', function () {
    $absence = pendingAbsence();

    expect($this->profUser->can('update', $absence))->toBeFalse()
        ->and($this->profUser->can('delete', $absence))->toBeFalse()
        // Vizualizarea și consemnarea îi rămân.
        ->and($this->profUser->can('view', $absence))->toBeTrue()
        ->and($this->profUser->can('create', Absence::class))->toBeTrue();
});

it('dirigintele editează și șterge absențele clasei lui, dar nu pe ale alteia', function () {
    $absence = pendingAbsence();

    expect($this->homeroomUser->can('update', $absence))->toBeTrue()
        ->and($this->homeroomUser->can('delete', $absence))->toBeTrue();

    // Aceeași absență, altă clasă: dirigintele nostru nu are nicio putere acolo.
    $altaClasa = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    $altElev = Student::factory()->create();
    Enrollment::factory()->for($altElev)->for($altaClasa)->for($this->year)->create();
    $straina = pendingAbsence(['school_class_id' => $altaClasa->id, 'student_id' => $altElev->id]);

    expect($this->homeroomUser->can('update', $straina))->toBeFalse()
        ->and($this->homeroomUser->can('delete', $straina))->toBeFalse();
});

// ─── Triajul din listă: acțiunile rapide + decizia în masă ───────────────────────────────

it('dirigintele fixează statutul dintr-un click, iar acțiunile dispar după decizie', function () {
    actingAs($this->homeroomUser);

    $absence = pendingAbsence();

    Livewire::test(ListAbsences::class)
        ->assertTableActionVisible('setMotivated', $absence)
        ->callTableAction('setMotivated', $absence);

    expect($absence->fresh()->is_motivated)->toBeTrue();

    // Decisă → butoanele de triaj nu mai au obiect.
    Livewire::test(ListAbsences::class)
        ->assertTableActionHidden('setMotivated', $absence->fresh())
        ->assertTableActionHidden('setUnmotivated', $absence->fresh());
});

it('profesorul nu vede butoanele de triaj — el doar consultă lista', function () {
    actingAs($this->profUser);

    $absence = pendingAbsence();

    Livewire::test(ListAbsences::class)
        ->assertTableActionHidden('setMotivated', $absence)
        ->assertTableActionHidden('setUnmotivated', $absence);
});

it('decizia în masă statutează doar rândurile clasei dirigintelui', function () {
    actingAs($this->homeroomUser);

    $aMea = pendingAbsence();

    // O absență dintr-o clasă străină, VIZIBILĂ doar administrației — dar cheia testului e că,
    // chiar selectată, autorizarea per rând o sare.
    $altaClasa = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    $altElev = Student::factory()->create();
    Enrollment::factory()->for($altElev)->for($altaClasa)->for($this->year)->create();
    $straina = pendingAbsence(['school_class_id' => $altaClasa->id, 'student_id' => $altElev->id, 'occurred_on' => '2026-03-11']);

    Livewire::test(ListAbsences::class)
        ->callTableBulkAction('bulkUnmotivated', [$aMea->getKey(), $straina->getKey()]);

    expect($aMea->fresh()->is_motivated)->toBeFalse()
        // Rândul străin a rămas nedecis: politica `update` l-a refuzat, acțiunea l-a sărit.
        ->and($straina->fresh()->is_motivated)->toBeNull();
});

// ─── Ștafeta: badge-ul din meniu ─────────────────────────────────────────────────────────

it('badge-ul de navigație numără doar coada proprie: dirigintele clasa lui, profesorul nimic', function () {
    pendingAbsence();

    actingAs($this->homeroomUser);
    expect(AbsenceResource::getNavigationBadge())->toBe('1');

    actingAs($this->profUser);
    expect(AbsenceResource::getNavigationBadge())->toBeNull();

    // După decizie, coada se golește.
    Absence::query()->update(['is_motivated' => false]);
    actingAs($this->homeroomUser->fresh());
    expect(AbsenceResource::getNavigationBadge())->toBeNull();
});

// ─── Sincronizarea cu catalogul: motivarea aprobată acoperă și absențele fără statut ─────

it('aprobarea unei motivări motivează și absențele încă fără statut din perioadă', function () {
    $absence = pendingAbsence();

    $motivation = AbsenceMotivation::create([
        'student_id' => $this->student->id,
        'requested_by_user_id' => $this->homeroomUser->id,
        'reason' => 'Certificat medical.',
        'period_start' => '2026-03-09',
        'period_end' => '2026-03-11',
        'status' => RequestStatus::Pending,
        'is_exception' => false,
    ]);

    $motivation->approve($this->homeroomUser->id);

    expect($absence->fresh()->is_motivated)->toBeTrue();
});

it('o absență consemnată într-o zi deja acoperită de motivare aprobată se naște motivată', function () {
    AbsenceMotivation::create([
        'student_id' => $this->student->id,
        'requested_by_user_id' => $this->homeroomUser->id,
        'reason' => 'Concurs.',
        'period_start' => '2026-03-10',
        'period_end' => '2026-03-10',
        'status' => RequestStatus::Approved,
        'is_exception' => false,
    ]);

    // Profesorul consemnează FĂRĂ statut, dar observerul vede dovada și decide în locul dirigintelui.
    $absence = pendingAbsence();

    expect($absence->fresh()->is_motivated)->toBeTrue();
});

// ─── Plasa de siguranță: consolidarea decide ce a rămas nedecis ──────────────────────────

it('consolidarea zilnică închide absențele fără statut cu termen expirat ca NEMOTIVATE', function () {
    $expirata = pendingAbsence([
        'occurred_on' => Carbon::today()->subDays(30)->toDateString(),
        'motivation_deadline' => Carbon::today()->subDays(20)->toDateString(),
    ]);
    // Termen încă deschis → rămâne nedecisă.
    $proaspata = pendingAbsence([
        'occurred_on' => Carbon::today()->subDay()->toDateString(),
        'motivation_deadline' => Carbon::today()->addDays(4)->toDateString(),
    ]);

    $this->artisan('app:consolidate-absences')->assertSuccessful();

    expect($expirata->fresh()->is_motivated)->toBeFalse()
        ->and($expirata->fresh()->motivation_locked_at)->not->toBeNull()
        ->and($proaspata->fresh()->is_motivated)->toBeNull()
        ->and($proaspata->fresh()->motivation_locked_at)->toBeNull();
});

// ─── Cabinetul familiei: absența fără statut nu e „nemotivată" ───────────────────────────

it('familia vede absența fără statut distinct, nu ca nemotivată', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($this->student->id);
    actingAs($parent);

    pendingAbsence();
    pendingAbsence(['occurred_on' => '2026-03-12', 'is_motivated' => false]);

    $response = $this->get('/cabinet/elev/'.$this->student->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('absencesTotal', 2)
            ->where('absencesMotivated', 0)
            ->where('absencesUnmotivated', 1)
            ->where('absencesPending', 1));
});
