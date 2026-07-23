<?php

/**
 * Intervalul PERSONALIZAT al barei temporale (cerința beneficiarului 2026-07-23): navigarea doar
 * cu săgeți făcea o dată îndepărtată practic inaccesibilă (o apăsare = o zi). Modul liber alege
 * direct capetele.
 *
 * Trait-ul `HasTimeNavigator` e COMUN celor trei module (Teme / Note / Absențe), deci scenariile
 * de graniță se probează o dată pe trait și separat pe fiecare modul se probează că filtrarea
 * ajunge la coloana LUI de dată (teme = data efectivă, note = graded_on, absențe = occurred_on
 * datetime — capătul superior trebuie să prindă toată ziua, nu doar miezul nopții).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
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
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'grade_level' => 7, 'section' => 'A']);
    $this->subject = Subject::factory()->create();

    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create([
        'enrolled_on' => '2025-09-01', 'left_on' => null,
    ]);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

/** Componenta „Note", deschisă direct în contextul clasei de test. */
function customRangePage(array $query = [])
{
    return Livewire::withQueryParams($query)->test(ListGrades::class);
}

it('intervalul liber filtrează pe capetele alese, indiferent cât de departe sunt', function () {
    $in = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-12']);
    $before = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-09']);
    $after = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-21']);

    customRangePage(['mod' => 'personalizat', 'de' => '2025-11-10', 'pana' => '2025-11-20'])
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$in])
        ->assertCanNotSeeTableRecords([$before, $after]);
});

it('interval de o SINGURĂ zi: capetele egale prind exact ziua aceea', function () {
    $inside = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-12']);
    $dayBefore = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-11']);
    $dayAfter = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-13']);

    $page = customRangePage(['mod' => 'personalizat', 'de' => '2025-11-12', 'pana' => '2025-11-12'])
        ->call('openCatalogEntity', $this->class->id);

    $page->assertCanSeeTableRecords([$inside])
        ->assertCanNotSeeTableRecords([$dayBefore, $dayAfter]);

    // Eticheta unei singure zile e ziua întreagă, nu „12 nov. – 12 nov.".
    expect($page->instance()->timePeriodLabel())->toContain('12');
});

it('interval peste LUNI și peste ANI diferiți', function () {
    $decembrie = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-12-28']);
    $ianuarie = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2026-01-06']);
    $inafara = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2026-02-02']);

    $page = customRangePage(['mod' => 'personalizat', 'de' => '2025-12-20', 'pana' => '2026-01-10'])
        ->call('openCatalogEntity', $this->class->id);

    $page->assertCanSeeTableRecords([$decembrie, $ianuarie])
        ->assertCanNotSeeTableRecords([$inafara]);

    // Ani diferiți → anul apare pe AMBELE capete (altfel „20 dec. – 10 ian. 2026" ar minți).
    expect($page->instance()->timePeriodLabel())->toContain('2025')
        ->and($page->instance()->timePeriodLabel())->toContain('2026');
});

it('capete DESCHISE: „din X încoace" și „până la Y" sunt intervale valide, nu erori', function () {
    $vechi = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-09-15']);
    $recent = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2026-01-20']);

    customRangePage(['mod' => 'personalizat', 'de' => '2025-12-01'])
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$vechi]);

    customRangePage(['mod' => 'personalizat', 'pana' => '2025-10-01'])
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$vechi])
        ->assertCanNotSeeTableRecords([$recent]);
});

it('perioadă FĂRĂ rezultate: lista e goală, dar filtrul rămâne activ și explicit', function () {
    $grade = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-12']);

    $page = customRangePage(['mod' => 'personalizat', 'de' => '2024-01-01', 'pana' => '2024-01-31'])
        ->call('openCatalogEntity', $this->class->id);

    $page->assertCanNotSeeTableRecords([$grade]);

    expect($page->instance()->timeRange())->not->toBeNull()
        ->and($page->instance()->timeCustomIsEmpty())->toBeFalse();
});

it('capete INVERSATE se schimbă între ele — o greșeală de tastare nu întoarce zero rezultate', function () {
    $grade = Grade::factory()->for($this->student)->for($this->subject)->for($this->class)->create(['graded_on' => '2025-11-12']);

    $page = customRangePage()->call('openCatalogEntity', $this->class->id);

    $page->call('setTimeMode', 'personalizat')
        ->set('timeFrom', '2025-11-20')
        ->set('timeUntil', '2025-11-10');

    expect($page->instance()->timeFrom)->toBe('2025-11-10')
        ->and($page->instance()->timeUntil)->toBe('2025-11-20');

    $page->assertCanSeeTableRecords([$grade]);
});

it('datele imposibile sau stricate din URL se ignoră — nu se rostogolesc tăcut în altă zi', function () {
    $page = customRangePage(['mod' => 'personalizat', 'de' => '2026-02-31', 'pana' => 'ieri'])
        ->call('openCatalogEntity', $this->class->id);

    // Ambele capete cad → intervalul e gol, cu îndrumare în bară (nu un filtru inventat).
    expect($page->instance()->timeRange())->toBeNull()
        ->and($page->instance()->timeCustomIsEmpty())->toBeTrue();
});

it('trecerea în „Personalizat" moștenește perioada privită; ieșirea curăță capetele (fără conflicte)', function () {
    $page = customRangePage(['mod' => 'luna', 'ref' => '2025-11-10'])
        ->call('openCatalogEntity', $this->class->id);

    $page->call('setTimeMode', 'personalizat');

    expect($page->instance()->timeFrom)->toBe('2025-11-01')
        ->and($page->instance()->timeUntil)->toBe('2025-11-30');

    // Revenirea la un filtru predefinit nu lasă capetele în urmă (s-ar reactiva tăcut).
    $page->call('setTimeMode', 'saptamana');

    expect($page->instance()->timeFrom)->toBeNull()
        ->and($page->instance()->timeUntil)->toBeNull()
        ->and($page->instance()->timeMode())->toBe('saptamana');

    // „Toate" oprește orice constrângere temporală.
    $page->call('setTimeMode', 'toate');
    expect($page->instance()->timeRange())->toBeNull();
});

it('săgețile nu mișcă intervalul liber (nu are „pas"), iar golirea îl reia de la zero', function () {
    $page = customRangePage(['mod' => 'personalizat', 'de' => '2025-11-10', 'pana' => '2025-11-20'])
        ->call('openCatalogEntity', $this->class->id);

    $page->call('shiftTimePeriod', 1);

    expect($page->instance()->timeFrom)->toBe('2025-11-10')
        ->and($page->instance()->timeUntil)->toBe('2025-11-20');

    $page->call('clearCustomRange');

    expect($page->instance()->timeCustomIsEmpty())->toBeTrue()
        ->and($page->instance()->timeMode())->toBe('personalizat');
});

it('ABSENȚE: capătul superior prinde toată ziua, deși coloana e datetime', function () {
    // Absență la ora 14:30 în ULTIMA zi a intervalului: cu un capăt fără oră ar fi ieșit din filtru.
    $seara = Absence::factory()->for($this->student)->for($this->subject)->for($this->class)->create([
        'occurred_on' => '2025-11-20 14:30:00',
    ]);
    $inafara = Absence::factory()->for($this->student)->for($this->subject)->for($this->class)->create([
        'occurred_on' => '2025-11-21 08:00:00',
    ]);

    Livewire::withQueryParams(['mod' => 'personalizat', 'de' => '2025-11-10', 'pana' => '2025-11-20'])
        ->test(ListAbsences::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$seara])
        ->assertCanNotSeeTableRecords([$inafara]);
});

it('TEME: intervalul liber filtrează pe data EFECTIVĂ (termenul primează asupra atribuirii)', function () {
    // Tema se leagă de clasă prin (treaptă, literă), nu prin FK — vezi HomeworkNavigatorTest.
    $dueInside = HomeworkAssignment::factory()->for($this->subject)->create([
        'grade_level' => 7, 'section' => 'A',
        'assigned_on' => '2025-11-03', 'due_on' => '2025-11-12',
    ]);
    $dueOutside = HomeworkAssignment::factory()->for($this->subject)->create([
        'grade_level' => 7, 'section' => 'A',
        'assigned_on' => '2025-11-12', 'due_on' => '2025-11-24',
    ]);

    Livewire::withQueryParams(['mod' => 'personalizat', 'de' => '2025-11-10', 'pana' => '2025-11-20'])
        ->test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$dueInside])
        ->assertCanNotSeeTableRecords([$dueOutside]);
});

it('pastila „Personalizat" există în bară, pe toate cele trei module', function () {
    foreach ([ListGrades::class, ListAbsences::class, ListHomeworkAssignments::class] as $page) {
        $pills = Livewire::test($page)->instance()->timePills();
        $keys = array_column($pills, 'key');

        expect($keys)->toContain('personalizat')
            ->and($keys)->toBe(['toate', 'zi', 'saptamana', 'luna', 'personalizat']);
    }
});
