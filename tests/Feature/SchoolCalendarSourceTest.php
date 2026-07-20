<?php

/**
 * SURSA UNICĂ a „momentului școlar curent" (LOT 3 al restructurării „Configurare").
 *
 * Sistemul avea două adevăruri: `terms.is_current` (derivat automat, citit peste tot) și
 * `academic_years.is_current` (toggle manual, un singur consumator). Coincideau cât timp cineva
 * bifa corect — dar la rollover scheduler-ul mută semestrul singur, iar flagul de an nu-l urmează.
 * Regula stabilită: semestrul e sursa, anul se derivă din el, iar flagul de pe an e doar oglinda.
 */

use App\Console\Commands\SyncCurrentTerm;
use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Terms\Pages\ListTerms;
use App\Filament\Widgets\AcademicYearNeedsTerms;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    AcademicYearNeedsTerms::flushCache();
});

it('anul curent se derivă din SEMESTRUL curent, nu din flagul de pe an', function () {
    $vechi = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true]);
    $nou = AcademicYear::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => false]);

    // Rollover: semestrul curent e deja în anul NOU, dar flagul de an a rămas pe cel vechi.
    Term::factory()->for($vechi)->create(['is_current' => false, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31']);
    Term::factory()->for($nou)->create(['is_current' => true, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);

    expect(SchoolCalendar::currentYearId())->toBe($nou->id)
        ->and(SchoolCalendar::currentYearId())->not->toBe($vechi->id);
});

it('sincronizarea zilnică oglindește anul curent pe flag, ca cele două să nu mai diveargă', function () {
    $vechi = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true]);
    $nou = AcademicYear::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => false]);

    Term::factory()->for($vechi)->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true]);
    Term::factory()->for($nou)->create([
        'starts_on' => Carbon::today()->subDays(5)->toDateString(),
        'ends_on' => Carbon::today()->addDays(60)->toDateString(),
        'is_current' => false,
    ]);

    $this->artisan(SyncCurrentTerm::class)->assertSuccessful();

    expect($nou->fresh()->is_current)->toBeTrue()
        ->and($vechi->fresh()->is_current)->toBeFalse()
        ->and(SchoolCalendar::currentYearId())->toBe($nou->id);
});

it('registrul de profesori numără diriginții pe anul DERIVAT, nu pe flagul manual', function () {
    $vechi = AcademicYear::factory()->create(['is_current' => true]);
    $nou = AcademicYear::factory()->create(['is_current' => false]);

    Term::factory()->for($nou)->create(['is_current' => true]);

    $dirigVechi = Teacher::factory()->create();
    $dirigNou = Teacher::factory()->create();
    SchoolClass::factory()->for($vechi)->create(['homeroom_teacher_id' => $dirigVechi->id]);
    SchoolClass::factory()->for($nou)->create(['homeroom_teacher_id' => $dirigNou->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)->call('openView', 'diriginti');

    // Dirigintele anului DERIVAT (nou) apare; cel al anului marcat manual (vechi) — nu.
    expect(collect($component->instance()->teacherCards())->pluck('id')->all())
        ->toBe([$dirigNou->id]);
});

it('fără niciun semestru curent, registrul nu inventează un an: vederea „Diriginți" e goală', function () {
    $an = AcademicYear::factory()->create(['is_current' => true]);
    $diriginte = Teacher::factory()->create();
    SchoolClass::factory()->for($an)->create(['homeroom_teacher_id' => $diriginte->id]);

    // Niciun Term cu is_current → SchoolCalendar::currentYearId() = null.
    expect(SchoolCalendar::currentYearId())->toBeNull();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class);

    // Numărătoarea e 0 (nu o eroare, nu tot corpul didactic), iar vederea e goală.
    expect(collect($component->instance()->viewPills())->firstWhere('key', 'diriginti')['count'])->toBe(0);

    $component->call('openView', 'diriginti');
    expect($component->instance()->teacherCards())->toBe([]);
});

it('widgetul semnalează anul fără semestre în fereastra de rollover, nu tot timpul', function () {
    $configurator = User::factory()->create();
    $configurator->assignRole(UserRole::AdministratorOperational->value);
    actingAs($configurator);

    // Anul curent se încheie PESTE MULT timp → anul viitor gol încă nu alarmează.
    $curent = AcademicYear::factory()->create([
        'starts_on' => Carbon::today()->subMonths(2)->toDateString(),
        'ends_on' => Carbon::today()->addMonths(6)->toDateString(),
    ]);
    Term::factory()->for($curent)->create(['is_current' => true]);
    AcademicYear::factory()->create(['starts_on' => Carbon::today()->addYear()->toDateString()]);

    AcademicYearNeedsTerms::flushCache();
    expect(AcademicYearNeedsTerms::canView())->toBeFalse();

    // Anul curent se apropie de final → semnalul devine relevant.
    $curent->update(['ends_on' => Carbon::today()->addDays(10)->toDateString()]);

    AcademicYearNeedsTerms::flushCache();
    expect(AcademicYearNeedsTerms::canView())->toBeTrue();
});

it('anul NOU, încă fără semestre, e navigabil prin ?an= — nu mai aterizezi tăcut în anul vechi', function () {
    $curent = AcademicYear::factory()->create();
    Term::factory()->for($curent)->create(['is_current' => true]);

    // An nou creat, complet gol: fără el în pastile, toate săriturile cădeau pe anul curent.
    $nou = AcademicYear::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::withQueryParams(['an' => (string) $nou->id])->test(ListTerms::class);
    $page = $component->instance();

    expect($page->activeYearId())->toBe($nou->id)
        ->and(collect($page->yearPills())->pluck('id')->all())->toContain($nou->id)
        // Badge 0 — anul e vizibil TOCMAI ca să se vadă că e gol.
        ->and(collect($page->yearPills())->firstWhere('id', $nou->id)['count'])->toBe(0);

    // Un id INEXISTENT rămâne respins: „gol" nu înseamnă „orice".
    $strain = Livewire::withQueryParams(['an' => '999999'])->test(ListTerms::class);
    expect($strain->instance()->activeYearId())->toBe($curent->id);
});

it('nota datată după finalul anului curent e refuzată când anul nou n-are semestre (gard de rollover)', function () {
    $an = AcademicYear::factory()->create([
        'starts_on' => Carbon::today()->subMonths(10)->toDateString(),
        'ends_on' => Carbon::today()->subDays(10)->toDateString(),
    ]);
    Term::factory()->for($an)->create([
        'starts_on' => Carbon::today()->subMonths(10)->toDateString(),
        'ends_on' => Carbon::today()->subDays(10)->toDateString(),
        'is_current' => true,
    ]);

    $scope = new class
    {
        use EnforcesGradeScope;

        /** @param array<string, mixed> $data @return array<string, mixed> */
        public function run(array $data): array
        {
            return $this->enforceGradeScope($data);
        }
    };

    // Data e DUPĂ finalul anului al cărui semestru e curent = septembrie fără an nou deschis.
    expect(fn () => $scope->run(['graded_on' => Carbon::today()->toDateString()]))
        ->toThrow(ValidationException::class);
});

it('widgetul e doar pentru configuratori', function () {
    $an = AcademicYear::factory()->create(['ends_on' => Carbon::today()->addDays(5)->toDateString()]);
    Term::factory()->for($an)->create(['is_current' => true]);
    AcademicYear::factory()->create(); // an gol, în fereastră

    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    AcademicYearNeedsTerms::flushCache();

    // PVD nu configurează școala (§3.3) — nu primește sarcina.
    expect(AcademicYearNeedsTerms::canView())->toBeFalse();
});
