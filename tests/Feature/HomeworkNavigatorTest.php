<?php

/**
 * Navigatorul de catalog al paginii „Teme" — ADAPTAT modelului temelor: dimensiuni fără
 * „Perioade", clasa contextului tradusă în (treaptă, literă) cu includerea temelor pe toată
 * treapta, agregate mapate pe clase și pre-completarea formularului din context.
 */

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    // Treapta 7 cu literele A și B; profesorul predă doar în 7 A.
    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'grade_level' => 7, 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'grade_level' => 7, 'section' => 'B']);
    $this->subject = Subject::factory()->create();
});

function hwNavTeacher(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

function hwNavDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

function hwFor(int $gradeLevel, ?string $section, Subject $subject, ?Teacher $teacher = null, string $on = '2025-10-10', ?string $due = null): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'grade_level' => $gradeLevel,
        'section' => $section,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'teacher_id' => $teacher?->id,
        'author_name' => $teacher?->full_name ?? 'Legacy',
        'assigned_on' => $on,
        'due_on' => $due,
    ]);
}

// ─── Dimensiuni adaptate temelor ─────────────────────────────────────────────────────────

it('temele nu au dimensiunea „Perioade"; „Profesori" rămâne doar pentru administrație', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));
    $teacherDims = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogDimensions();
    expect($teacherDims)->toHaveKeys(['clase', 'discipline'])
        ->not->toHaveKey('perioade')
        ->not->toHaveKey('profesori');

    actingAs(hwNavDirector());
    $adminDims = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogDimensions();
    expect($adminDims)->toHaveKeys(['clase', 'discipline', 'profesori'])
        ->not->toHaveKey('perioade');
});

// ─── Contextul de clasă = treaptă + literă, cu temele pe toată treapta ───────────────────

it('contextul clasei include temele literei EI și temele pe toată treapta, nu ale altei litere', function () {
    actingAs(hwNavDirector());

    $forA = hwFor(7, 'A', $this->subject);
    $wholeGrade = hwFor(7, null, $this->subject);
    $forB = hwFor(7, 'B', $this->subject);

    Livewire::test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->classA->id)
        ->assertCanSeeTableRecords([$forA, $wholeGrade])
        ->assertCanNotSeeTableRecords([$forB]);
});

it('cardul clasei agregă și temele pe toată treapta (numărul include litera + treapta)', function () {
    actingAs(hwNavDirector());

    hwFor(7, 'A', $this->subject, on: '2025-10-01');
    hwFor(7, null, $this->subject, on: '2025-11-05');

    $cards = collect(Livewire::test(ListHomeworkAssignments::class)->instance()->catalogEntityCards());

    $cardA = $cards->firstWhere('id', $this->classA->id);
    $cardB = $cards->firstWhere('id', $this->classB->id);

    // 7 A: tema ei + cea pe treaptă = 2; 7 B: doar cea pe treaptă = 1.
    expect($cardA['stats'])->toContain(trans_choice('panel.catalog_nav.homework_records', 2, ['count' => 2]))
        ->and($cardB['stats'])->toContain(trans_choice('panel.catalog_nav.homework_records', 1, ['count' => 1]));
});

it('profesorul vede doar clasele lui drept carduri (teme)', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    $cards = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->classA->id]);
});

it('un ?perioada= rătăcit în URL nu deschide niciun context la teme', function () {
    actingAs(hwNavDirector());

    $component = Livewire::withQueryParams(['vedere' => 'perioade', 'perioada' => '1'])
        ->test(ListHomeworkAssignments::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse()
        // Dimensiunea invalidă cade pe „clase".
        ->and($component->instance()->catalogActiveDimension())->toBe('clase');
});

// ─── Pre-completarea formularului din context (clasa → treaptă + literă) ─────────────────

it('formularul de temă se pre-completează cu ținta (clasa) și disciplina din context', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id, 'disciplina' => (string) $this->subject->id])
        ->test(CreateHomeworkAssignment::class)
        ->assertFormSet([
            'class_target' => 'class:'.$this->classA->id,
            'subject_id' => $this->subject->id,
        ]);
});

it('o clasă din afara alocărilor profesorului nu se pre-completează în formularul de temă', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->classB->id])
        ->test(CreateHomeworkAssignment::class)
        ->assertFormSet([
            'class_target' => null,
        ]);
});

// ─── Componenta TEMPORALĂ (2026-07-18): bara Zi/Săptămână/Lună + cronologie pe data efectivă ──

it('modul „săptămână" filtrează pe DATA EFECTIVĂ: termenul decide, cu fallback pe atribuire la legacy', function () {
    actingAs(hwNavDirector());

    // Termen ÎN săptămâna de referință (10-16 nov 2025), deși atribuită în afara ei.
    $dueInWeek = hwFor(7, 'A', $this->subject, on: '2025-11-03', due: '2025-11-12');
    // Legacy fără termen, atribuită în săptămână → efectiva = atribuirea, intră.
    $legacyInWeek = hwFor(7, 'A', $this->subject, on: '2025-11-13');
    // Termen în ALTĂ săptămână → iese, chiar dacă atribuirea cade în săptămână.
    $dueOutside = hwFor(7, 'A', $this->subject, on: '2025-11-12', due: '2025-11-24');

    Livewire::withQueryParams(['mod' => 'saptamana', 'ref' => '2025-11-10'])
        ->test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->classA->id)
        ->assertCanSeeTableRecords([$dueInWeek, $legacyInWeek])
        ->assertCanNotSeeTableRecords([$dueOutside]);

    // Mod/ref INVALIDE din URL → „Toate" (nu se ia nimic de bun).
    $component = Livewire::withQueryParams(['mod' => 'trimestru', 'ref' => 'nu-e-data'])
        ->test(ListHomeworkAssignments::class)
        ->instance();

    expect($component->timeMode())->toBeNull()
        ->and($component->timeRef()->isToday())->toBeTrue();
});

it('navigarea pe perioadă: ◀ ▶ mută referința cu pasul modului, „Azi" o resetează', function () {
    actingAs(hwNavDirector());

    $component = Livewire::withQueryParams(['mod' => 'saptamana', 'ref' => '2025-11-10'])
        ->test(ListHomeworkAssignments::class);

    $component->call('shiftTimePeriod', 1);
    expect($component->instance()->timeRef()->toDateString())->toBe('2025-11-17');

    $component->call('shiftTimePeriod', -1);
    $component->call('shiftTimePeriod', -1);
    expect($component->instance()->timeRef()->toDateString())->toBe('2025-11-03');

    $component->call('goToTimeToday');
    expect($component->instance()->timeRef()->isToday())->toBeTrue();
});

it('cabinetul livrează temele cronologic: azi+viitoare ASC întâi (cu status), apoi istoricul DESC', function () {
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $elev->id]);
    Enrollment::factory()->for($student)->for($this->classA)->for($this->year)->create();

    $today = now()->toDateString();
    hwFor(7, 'A', $this->subject, on: now()->subDays(10)->toDateString(), due: now()->subDays(3)->toDateString());
    hwFor(7, 'A', $this->subject, on: now()->subDay()->toDateString(), due: now()->addDays(2)->toDateString());
    hwFor(7, 'A', $this->subject, on: $today, due: $today);

    $items = actingAs($elev)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'homework'))
        ->assertOk()
        ->json('props.homework');

    expect(collect($items)->pluck('status')->all())->toBe(['today', 'upcoming', 'past'])
        ->and($items[0]['effectiveDate'])->toBe($today)
        ->and($items[0]['due'])->toBe(now()->format('d.m.Y'))
        ->and($items[2]['due'])->toBe(now()->subDays(3)->format('d.m.Y'));
});

it('cronologia implicită: temele se ordonează pe data efectivă (termenul primează asupra atribuirii)', function () {
    actingAs(hwNavDirector());

    // Atribuită DEVREME dar cu termen TÂRZIU → prima în cronologia desc.
    $lateDue = hwFor(7, 'A', $this->subject, on: '2025-10-01', due: '2025-12-01');
    $legacy = hwFor(7, 'A', $this->subject, on: '2025-11-10');
    $earlyDue = hwFor(7, 'A', $this->subject, on: '2025-11-01', due: '2025-11-05');

    Livewire::test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->classA->id)
        ->assertCanSeeTableRecords([$lateDue, $legacy, $earlyDue], inOrder: true);
});
