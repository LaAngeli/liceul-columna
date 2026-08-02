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
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\EditHoliday;
use App\Filament\Resources\Holidays\Pages\LegalHolidaysGenerator;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Models\Term;
use App\Models\User;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
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
        'name' => '2025–2026',
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
        'name' => '2024–2025',
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

// ─── Restructurare raportată 2026-08-03 ──────────────────────────────────────────────────

it('ziua liberă nu poate fi pusă în afara anului școlar — nici la creare, nici la editare', function () {
    plannerUser();
    $year = plannerYear(); // 01.09.2025 – 31.08.2026

    // Fereastra dintre ani: 15.09.2026 nu aparține niciunui an configurat.
    Livewire::withQueryParams(['an' => $year->id])
        ->test(CreateHoliday::class)
        ->fillForm(['name' => 'În afara anului', 'type' => HolidayType::InstitutionalDay->value, 'starts_on' => '2026-09-15'])
        ->call('create')
        ->assertHasFormErrors(['starts_on']);

    expect(Holiday::query()->count())->toBe(0);

    // Interiorul anului trece.
    Livewire::withQueryParams(['an' => $year->id])
        ->test(CreateHoliday::class)
        ->fillForm(['name' => 'În an', 'type' => HolidayType::InstitutionalDay->value, 'starts_on' => '2026-03-16'])
        ->call('create')
        ->assertHasNoFormErrors();

    $holiday = Holiday::query()->sole();

    // Editarea respectă aceleași limite (anul se deduce din ziua editată).
    Livewire::test(EditHoliday::class, ['record' => $holiday->getRouteKey()])
        ->fillForm(['ends_on' => '2026-09-20'])
        ->call('save')
        ->assertHasFormErrors(['ends_on']);
});

it('după creare se întoarce în PLANIFICATOR, pe anul zilei adăugate — nu în formularul de editare', function () {
    plannerUser();
    $year = plannerYear();

    Livewire::withQueryParams(['an' => $year->id])
        ->test(CreateHoliday::class)
        ->fillForm(['name' => 'Zi nouă', 'type' => HolidayType::InstitutionalDay->value, 'starts_on' => '2026-03-16'])
        ->call('create')
        ->assertRedirect(HolidayResource::getUrl('index', ['an' => $year->id]));
});

it('yearContaining găsește anul unei date și întoarce null pentru golul dintre ani', function () {
    $year = plannerYear(); // 01.09.2025 – 31.08.2026

    expect(SchoolCalendar::yearContaining(Carbon::parse('2026-03-16'))?->id)->toBe($year->id)
        ->and(SchoolCalendar::yearContaining(Carbon::parse('2026-09-15')))->toBeNull();
});

it('generatorul separă propunerile de adăugat de cele existente și comută anul din pagină', function () {
    plannerUser();
    $year = plannerYear();
    $other = AcademicYear::factory()->create([
        'name' => '2027–2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-08-31',
    ]);

    $page = Livewire::test(LegalHolidaysGenerator::class)->instance();
    $totalCandidates = count($page->candidateRows());

    expect($page->pendingRows())->toHaveCount($totalCandidates)
        ->and($page->existingRows())->toBeEmpty();

    // O sărbătoare introdusă trece din „de adăugat" în „deja în calendar" — fără bifă moartă.
    $component = Livewire::test(LegalHolidaysGenerator::class)
        ->fillForm(['selected' => ['2025-12-25|'.__('panel.holiday_planner.legal.christmas_new')]])
        ->call('create');

    $page = Livewire::test(LegalHolidaysGenerator::class)->instance();

    expect($page->existingRows())->toHaveCount(1)
        ->and($page->pendingRows())->toHaveCount($totalCandidates - 1)
        // Bifate implicit sunt DOAR cele care se pot adăuga.
        ->and(array_keys($page->pendingRows()))->not->toContain('2025-12-25|'.__('panel.holiday_planner.legal.christmas_new'));

    // Comutarea anului din pagină schimbă lista (fără a umbla la URL).
    $switched = Livewire::test(LegalHolidaysGenerator::class);
    $switched->call('openYear', $other->id);

    expect($switched->instance()->activeYear()?->id)->toBe($other->id)
        ->and($switched->instance()->existingRows())->toBeEmpty();

    // Pastilele arată câte mai sunt de adăugat per an.
    $pills = collect($switched->instance()->yearPills());
    expect($pills->firstWhere('id', $year->id)['pending'])->toBe($totalCandidates - 1)
        ->and($pills->firstWhere('id', $other->id)['active'])->toBeTrue();
});
