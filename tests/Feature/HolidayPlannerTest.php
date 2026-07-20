<?php

/**
 * Planificatorul zilelor libere: anul școlar văzut ca CALENDAR + cronologie (nu tabel), categorii
 * pe tipuri (HolidayType), generatorul sărbătorilor legale RM (Paștele ortodox CALCULAT, nu
 * hardcodat), integrarea cu formularul de absențe (avertisment pe zi liberă) și curățarea [DEMO].
 */

use App\Actions\GenerateLegalHolidays;
use App\Enums\HolidayType;
use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Holidays\Pages\LegalHolidaysGenerator;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Models\Term;
use App\Models\User;
use App\Support\Holidays;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function plannerUser(UserRole $role = UserRole::AdministratorOperational): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    return $user;
}

function plannerYear(): AcademicYear
{
    $year = AcademicYear::factory()->create([
        'name' => '2025-2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    Term::factory()->for($year)->create(['is_current' => true]);

    return $year;
}

it('calculează Paștele ortodox corect — regresie pe ani cu date cunoscute', function (int $year, string $expected) {
    expect(app(GenerateLegalHolidays::class)->orthodoxEaster($year)->toDateString())->toBe($expected);
})->with([
    '2025' => [2025, '2025-04-20'],
    '2026' => [2026, '2026-04-12'],
    '2027' => [2027, '2027-05-02'],
]);

it('generatorul propune sărbătorile din interiorul anului școlar, cu sărbătorile mobile calculate', function () {
    $candidates = app(GenerateLegalHolidays::class)
        ->candidatesBetween(Carbon::parse('2025-09-01'), Carbon::parse('2026-08-31'));

    $byName = collect($candidates)->keyBy('name');

    expect($byName->get(__('panel.holiday_planner.legal.easter'))['starts_on'])->toBe('2026-04-12')
        ->and($byName->get(__('panel.holiday_planner.legal.easter'))['ends_on'])->toBe('2026-04-13')
        ->and($byName->get(__('panel.holiday_planner.legal.memorial_easter'))['starts_on'])->toBe('2026-04-20')
        ->and($byName->get(__('panel.holiday_planner.legal.christmas_new'))['starts_on'])->toBe('2025-12-25')
        ->and($byName->get(__('panel.holiday_planner.legal.christmas_old'))['starts_on'])->toBe('2026-01-07')
        ->and($byName->get(__('panel.holiday_planner.legal.christmas_old'))['ends_on'])->toBe('2026-01-08')
        ->and($byName->get(__('panel.holiday_planner.legal.independence_day'))['starts_on'])->toBe('2026-08-27');

    // Nimic din afara intervalului (27.08.2025 e înaintea lui 1 septembrie).
    $outside = collect($candidates)
        ->pluck('starts_on')
        ->filter(fn (string $date): bool => $date < '2025-09-01' || $date > '2026-08-31');

    expect($outside)->toBeEmpty();
});

it('creează doar candidații bifați, cu tip legal, fără dubluri la a doua rulare', function () {
    $action = app(GenerateLegalHolidays::class);
    $from = Carbon::parse('2025-09-01');
    $to = Carbon::parse('2026-08-31');

    $keys = collect($action->candidatesBetween($from, $to))
        ->map(fn (array $candidate): string => $candidate['starts_on'].'|'.$candidate['name'])
        ->all();

    $created = $action->create($from, $to, $keys);

    expect($created)->toBeGreaterThanOrEqual(12)
        ->and(Holiday::query()->where('type', HolidayType::LegalHoliday->value)->count())->toBe($created)
        // Idempotent: a doua rulare nu dublează nimic.
        ->and($action->create($from, $to, $keys))->toBe(0);
});

it('planificatorul arată vacanța în calendar și cronologie și filtrează pe categorie', function () {
    plannerUser();
    $year = plannerYear();

    Holiday::create([
        'name' => 'Vacanța de iarnă',
        'type' => HolidayType::Vacation,
        'starts_on' => '2025-12-20',
        'ends_on' => '2026-01-07',
    ]);
    Holiday::create([
        'name' => 'Ziua Independenței',
        'type' => HolidayType::LegalHoliday,
        'starts_on' => '2026-08-27',
    ]);

    // Alt an școlar, cu propria zi liberă — nu are ce căuta în planificatorul lui 2025-2026.
    AcademicYear::factory()->create([
        'name' => '2024-2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    Holiday::create([
        'name' => 'Vacanța anului trecut',
        'type' => HolidayType::Vacation,
        'starts_on' => '2025-03-01',
        'ends_on' => '2025-03-09',
    ]);

    Livewire::test(ListHolidays::class, ['yearParam' => $year->id])
        ->assertSee('Vacanța de iarnă')
        ->assertSee('Ziua Independenței')
        // 20.12 – 07.01 = 19 zile calendaristice.
        ->assertSee('19 zile')
        ->assertDontSee('Vacanța anului trecut');

    // Filtrul pe categorie: în calendar/cronologie rămân doar vacanțele. Numele sărbătorii mai
    // poate apărea în „următoarea zi liberă" (eroul ignoră deliberat filtrele) — de aceea se
    // verifică absența DATEI ei din cronologie, nu a numelui.
    Livewire::test(ListHolidays::class, ['yearParam' => $year->id, 'typeParam' => HolidayType::Vacation->value])
        ->assertSee('Vacanța de iarnă')
        ->assertDontSee('27.08.2026');
});

it('căutarea filtrează după denumire', function () {
    plannerUser();
    $year = plannerYear();

    Holiday::create([
        'name' => 'Vacanța de primăvară',
        'type' => HolidayType::Vacation,
        'starts_on' => '2026-03-05',
        'ends_on' => '2026-03-12',
    ]);
    Holiday::create([
        'name' => 'Ziua Liceului',
        'type' => HolidayType::InstitutionalDay,
        'starts_on' => '2026-05-15',
    ]);

    Livewire::test(ListHolidays::class, ['yearParam' => $year->id])
        ->set('search', 'Liceului')
        ->assertSee('Ziua Liceului')
        ->assertDontSee('Vacanța de primăvară');
});

it('cititorii văd planificatorul fără afordanțe de scriere; operaționalul le are', function () {
    $year = plannerYear();

    $holiday = Holiday::create([
        'name' => 'Vacanța de iarnă',
        'type' => HolidayType::Vacation,
        'starts_on' => '2025-12-20',
        'ends_on' => '2026-01-07',
    ]);

    // Profesorul: vede imaginea, dar fără generator și fără linkuri de editare.
    plannerUser(UserRole::Profesor);

    Livewire::test(ListHolidays::class, ['yearParam' => $year->id])
        ->assertSee('Vacanța de iarnă')
        ->assertDontSee(__('panel.holiday_planner.generator.action'))
        ->assertDontSee("holidays/{$holiday->id}/edit");

    // Operaționalul: generator prezent + editare din cronologie/calendar.
    plannerUser();

    Livewire::test(ListHolidays::class, ['yearParam' => $year->id])
        ->assertSee(__('panel.holiday_planner.generator.action'))
        ->assertSee("holidays/{$holiday->id}/edit");
});

it('pagina generatorului creează sărbătorile bifate; cititorii primesc 403', function () {
    plannerUser();
    $year = plannerYear();

    // Selecție parțială REALĂ: doar Crăciunul pe stil nou.
    Livewire::test(LegalHolidaysGenerator::class)
        ->fillForm(['selected' => ['2025-12-25|'.__('panel.holiday_planner.legal.christmas_new')]])
        ->call('create')
        ->assertNotified();

    expect(Holiday::query()->count())->toBe(1)
        ->and(Holiday::query()->first()?->type)->toBe(HolidayType::LegalHoliday)
        ->and(Holiday::query()->first()?->starts_on->toDateString())->toBe('2025-12-25');

    // A doua vizită: propunerea deja existentă e marcată și BLOCATĂ, nu re-bifată implicit.
    $component = Livewire::test(LegalHolidaysGenerator::class);
    $selected = $component->get('data.selected');

    expect($selected)->not->toContain('2025-12-25|'.__('panel.holiday_planner.legal.christmas_new'))
        ->and(count($selected))->toBeGreaterThanOrEqual(10);

    // Cititorii planificatorului nu au ce căuta în generator.
    plannerUser(UserRole::Profesor);

    Livewire::test(LegalHolidaysGenerator::class)->assertForbidden();
});

it('holidayOn întoarce ziua liberă a datei; la suprapunere câștigă cea mai specifică', function () {
    Holiday::create([
        'name' => 'Vacanța de iarnă',
        'type' => HolidayType::Vacation,
        'starts_on' => '2025-12-20',
        'ends_on' => '2026-01-07',
    ]);
    Holiday::create([
        'name' => 'Crăciunul (stil nou)',
        'type' => HolidayType::LegalHoliday,
        'starts_on' => '2025-12-25',
    ]);

    expect(Holidays::holidayOn(Carbon::parse('2025-12-25'))?->name)->toBe('Crăciunul (stil nou)')
        ->and(Holidays::holidayOn(Carbon::parse('2025-12-27'))?->name)->toBe('Vacanța de iarnă')
        ->and(Holidays::holidayOn(Carbon::parse('2026-02-01')))->toBeNull();
});

it('formularul de absență avertizează când data cade într-o zi liberă', function () {
    Holiday::create([
        'name' => 'Vacanța de iarnă',
        'type' => HolidayType::Vacation,
        'starts_on' => '2025-12-20',
        'ends_on' => '2026-01-07',
    ]);

    plannerUser(UserRole::Director);

    Livewire::test(CreateAbsence::class)
        ->fillForm(['occurred_on' => '2025-12-25'])
        ->assertSee('Vacanța de iarnă');
});

it('app:purge-demo-data curăță zilele libere demo — ambele denumiri — și păstrează realele', function () {
    Holiday::create([
        'name' => '[DEMO] Zi liberă',
        'type' => HolidayType::InstitutionalDay,
        'starts_on' => '2026-05-20',
    ]);
    Holiday::create(['name' => 'Zi liberă (demo)', 'starts_on' => '2026-06-20']);
    $real = Holiday::create([
        'name' => 'Vacanța de vară',
        'type' => HolidayType::Vacation,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-08-31',
    ]);

    artisan('app:purge-demo-data')->assertSuccessful();

    expect(Holiday::query()->count())->toBe(1)
        ->and(Holiday::query()->first()?->id)->toBe($real->id);
});

it('etichetele HolidayType există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (HolidayType::cases() as $type) {
            expect($type->label())
                ->not->toBe('enums.holiday_type.'.$type->value, "Lipsește eticheta {$type->value} în {$locale}");
        }
    }

    app()->setLocale('ro');
});
