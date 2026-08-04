<?php

/**
 * Meniul cantinei (cerință 2026-08-01, UI v2 = planificator săptămânal): scrierea aparține
 * EXCLUSIV administratorului operațional (super-adminul păstrează break-glass), consultarea e a
 * întregii platforme — personalul în planificatorul din panou, familia în cabinet. Fără cache:
 * o salvare din panou se vede în cabinet la următorul request.
 */

use App\Enums\UserRole;
use App\Filament\Resources\CanteenMenus\Pages\CreateCanteenMenu;
use App\Models\CanteenMenu;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

afterEach(fn () => Carbon::setTestNow());

function canteenUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('scrierea e exclusiv a administratorului operațional; consultarea e a întregului personal', function () {
    $ao = canteenUser(UserRole::AdministratorOperational);
    $profesor = canteenUser(UserRole::Profesor);
    $director = canteenUser(UserRole::Director);
    $super = canteenUser(UserRole::Admin);

    $menu = CanteenMenu::factory()->create(['menu_date' => SchoolCalendar::localNow()->toDateString()]);

    // Toți cei din panou pot consulta planificatorul.
    $this->actingAs($ao)->get('/admin/canteen-menus')->assertOk();
    $this->actingAs($profesor)->get('/admin/canteen-menus')->assertOk();
    $this->actingAs($director)->get('/admin/canteen-menus')->assertOk();

    // Crearea: DOAR administratorul operațional + break-glass-ul super-adminului. Directorul,
    // deși administrație academică, NU gestionează meniul — cerința spune „exclusiv AO".
    $this->actingAs($ao)->get('/admin/canteen-menus/create')->assertOk();
    $this->actingAs($super)->get('/admin/canteen-menus/create')->assertOk();
    $this->actingAs($profesor)->get('/admin/canteen-menus/create')->assertForbidden();
    $this->actingAs($director)->get('/admin/canteen-menus/create')->assertForbidden();

    // Editarea urmează aceeași linie — verificat pe gardă, nu doar pe pagina de creare.
    $this->actingAs($director)->get("/admin/canteen-menus/{$menu->id}/edit")->assertForbidden();
    $this->actingAs($ao)->get("/admin/canteen-menus/{$menu->id}/edit")->assertOk();
});

it('planificatorul arată comenzile DOAR administratorului operațional — cititorii nu văd niciun buton', function () {
    // Regresie (raportat 2026-08-01): directorul vedea „Adăugare meniu" care ducea la 403.
    $ao = canteenUser(UserRole::AdministratorOperational);
    $director = canteenUser(UserRole::Director);
    $profesor = canteenUser(UserRole::Profesor);

    $menu = CanteenMenu::factory()->create(['menu_date' => SchoolCalendar::localNow()->toDateString()]);

    // AO: adăugare în antet + „Modifică" pe ziua publicată + „Adaugă" pe zilele goale.
    $this->actingAs($ao)->get('/admin/canteen-menus')
        ->assertOk()
        ->assertSee('canteen-menus/create')
        ->assertSee("canteen-menus/{$menu->id}/edit")
        // Ancorajul regulii responsive a antetului (pe telefon butonul stă în dreptul titlului):
        // stilul e în panou, dar clasa vine de aici — dispărută, regula ar tăcea.
        ->assertSee('fi-canteen-planner');

    // Cititorii: aceeași grilă, ZERO linkuri de scriere.
    foreach ([$director, $profesor] as $reader) {
        $this->actingAs($reader)->get('/admin/canteen-menus')
            ->assertOk()
            ->assertSee($menu->lunch_second)
            ->assertDontSee('canteen-menus/create')
            ->assertDontSee("canteen-menus/{$menu->id}/edit");
    }
});

it('axa temporală comută vederea: zi/săptămână/lună planifică, toate/personalizat consultă', function () {
    // Miercuri, pe ORA ȘCOLII.
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00', SchoolCalendar::TIMEZONE));
    $this->actingAs(canteenUser(UserRole::AdministratorOperational));

    CanteenMenu::factory()->create(['menu_date' => '2026-09-09', 'lunch_second' => 'FEL-SAPTAMANA-CURENTA']);
    CanteenMenu::factory()->create(['menu_date' => '2026-09-21', 'lunch_second' => 'FEL-ACEEASI-LUNA']);
    CanteenMenu::factory()->create(['menu_date' => '2026-10-05', 'lunch_second' => 'FEL-LUNA-VIITOARE']);

    // Implicit (fără ?mod) = Săptămâna curentă: grila ei, cu restul perioadelor nevăzute.
    $this->get('/admin/canteen-menus')
        ->assertOk()
        ->assertSee('FEL-SAPTAMANA-CURENTA')
        ->assertDontSee('FEL-ACEEASI-LUNA')
        ->assertDontSee('FEL-LUNA-VIITOARE');

    // Zi: doar ziua de referință.
    $this->get('/admin/canteen-menus?mod=zi')
        ->assertOk()
        ->assertSee('FEL-SAPTAMANA-CURENTA')
        ->assertDontSee('FEL-ACEEASI-LUNA');

    // Luna: toate săptămânile lunii de referință.
    $this->get('/admin/canteen-menus?mod=luna')
        ->assertOk()
        ->assertSee('FEL-SAPTAMANA-CURENTA')
        ->assertSee('FEL-ACEEASI-LUNA')
        ->assertDontSee('FEL-LUNA-VIITOARE');

    // Toate: arhiva completă, indiferent de perioadă.
    $this->get('/admin/canteen-menus?mod=toate')
        ->assertOk()
        ->assertSee('FEL-SAPTAMANA-CURENTA')
        ->assertSee('FEL-ACEEASI-LUNA')
        ->assertSee('FEL-LUNA-VIITOARE');

    // Personalizat: doar intervalul ales.
    $this->get('/admin/canteen-menus?mod=personalizat&de=2026-10-01&pana=2026-10-31')
        ->assertOk()
        ->assertSee('FEL-LUNA-VIITOARE')
        ->assertDontSee('FEL-SAPTAMANA-CURENTA');
});

it('administratorul operațional creează o zi de meniu; a doua zi pe aceeași dată e respinsă', function () {
    $this->actingAs(canteenUser(UserRole::AdministratorOperational));

    Livewire::test(CreateCanteenMenu::class)
        ->fillForm([
            'menu_date' => '2026-09-07',
            'breakfast_main' => 'Terci din crupe de gris cu lapte',
            'lunch_first' => 'Supă din linte roșie',
            'lunch_second' => 'Pârjoală din carne de vițel',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // whereDate, nu where: pe SQLite coloana `date` poartă și ora („… 00:00:00").
    expect(CanteenMenu::query()->whereDate('menu_date', '2026-09-07')->exists())->toBeTrue();

    // Aceeași dată a doua oară → validare pe formular, nu eroare de index unic.
    Livewire::test(CreateCanteenMenu::class)
        ->fillForm(['menu_date' => '2026-09-07'])
        ->call('create')
        ->assertHasFormErrors(['menu_date']);
});

it('din planificator, ziua vine gata aleasă, iar „Preia de săptămâna trecută" umple rubricile sursei', function () {
    $this->actingAs(canteenUser(UserRole::AdministratorOperational));

    $source = CanteenMenu::factory()->create([
        'menu_date' => '2026-09-07',
        'lunch_second' => 'Gulaș din carne de porc cu legume înăbușite',
    ]);

    Livewire::withQueryParams(['data' => '2026-09-14', 'sursa' => (string) $source->id])
        ->test(CreateCanteenMenu::class)
        ->assertSchemaStateSet([
            'menu_date' => '2026-09-14',
            'lunch_second' => 'Gulaș din carne de porc cu legume înăbușite',
            'breakfast_main' => $source->breakfast_main,
        ]);

    // Sursa coruptă e ignorată, nu o eroare — data rămâne cea a zilei-țintă.
    Livewire::withQueryParams(['data' => '2026-09-14', 'sursa' => 'abc'])
        ->test(CreateCanteenMenu::class)
        ->assertSchemaStateSet(['menu_date' => '2026-09-14', 'lunch_second' => null]);
});

it('familia vede în cabinet meniul săptămânii, cu ziua curentă evidențiată', function () {
    // Miercuri, pe ORA ȘCOLII (regula fusului: setTestNow fără fus explicit ar testa altă zi).
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00', SchoolCalendar::TIMEZONE));

    CanteenMenu::factory()->create([
        'menu_date' => '2026-09-09',
        'lunch_second' => 'Gulaș din carne de porc cu legume înăbușite',
        'lunch_side' => null,
    ]);

    $parinte = canteenUser(UserRole::Parinte);

    $this->actingAs($parinte)
        ->get('/cabinet/meniu')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cabinet/meniu')
            ->where('today', '2026-09-09')
            // Implicit = săptămâna, ca în planificatorul din panou.
            ->where('period.mode', 'saptamana')
            ->where('period.isCurrent', true)
            ->has('groups', 1)
            // Luni–vineri prezente chiar fără meniu; miercuri poartă datele publicate.
            ->has('groups.0.days', 5)
            ->where('groups.0.days.2.isToday', true)
            ->where('groups.0.days.2.menu.lunch.1.value', 'Gulaș din carne de porc cu legume înăbușite')
            // Rubricile necompletate NU se trimit — clientul nu afișează rânduri goale.
            ->where('groups.0.days.2.menu.lunch', fn ($rows) => collect($rows)->pluck('label')->doesntContain(__('panel.forms.canteen.lunch_side')))
            ->where('groups.0.days.0.menu', null));

    // Navigarea pe altă săptămână: ancora mută intervalul, ziua rămâne marcată doar azi.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?ref=2026-09-16')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period.isCurrent', false)
            ->where('groups.0.days.2.isToday', false));

    // `?data=` rămâne acceptat: linkurile trimise înainte de bara temporală nu se rup.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?data=2026-09-16')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('period.isCurrent', false));

    // Ancoră coruptă → cade pe azi, nu pe excepție.
    $this->actingAs($parinte)->get('/cabinet/meniu?data=abc')->assertOk();
});

it('cabinetul filtrează pe perioadă cu ACEEAȘI semantică precum panoul', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00', SchoolCalendar::TIMEZONE));

    // O zi în săptămâna curentă și una cu mult în urmă — separă „perioada" de „arhivă".
    CanteenMenu::factory()->create(['menu_date' => '2026-09-09', 'lunch_second' => 'Gulaș']);
    CanteenMenu::factory()->create(['menu_date' => '2026-06-03', 'lunch_second' => 'Tocană']);

    $parinte = canteenUser(UserRole::Parinte);

    // ZI: exact ziua cerută, chiar dacă e goală (altfel modul n-ar arăta nimic, fără explicație).
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?mod=zi&ref=2026-09-10')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period.mode', 'zi')
            ->has('groups.0.days', 1)
            ->where('groups.0.days.0.date', '2026-09-10')
            ->where('groups.0.days.0.menu', null));

    // LUNA: săptămâni ÎNTREGI care ating luna — unitatea de lucru a cantinei e săptămâna.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?mod=luna&ref=2026-09-09')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period.mode', 'luna')
            ->has('groups', 5)
            ->where('groups.0.label', fn ($label) => is_string($label) && $label !== ''));

    // TOATE: doar zilele CU meniu, recente întâi — consultare, fără zile goale. Din 04.08.2026,
    // „toate" înseamnă pentru familie fereastra PUBLICĂ: ziua din iunie e lună încheiată, deci a
    // trecut în istoricul administratorului operațional și nu mai apare aici.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?mod=toate')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period.mode', 'toate')
            ->has('groups', 1)
            ->where('groups.0.days.0.date', '2026-09-09'));

    // PERSONALIZAT: intervalul taie arhiva; ziua din iunie rămâne pe dinafară.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?mod=personalizat&de=2026-09-01&pana=2026-09-30')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period.mode', 'personalizat')
            ->where('period.from', '2026-09-01')
            ->has('groups', 1)
            ->where('groups.0.days.0.date', '2026-09-09'));

    // Mod necunoscut → implicit, nu eroare.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?mod=nuexista')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('period.mode', 'saptamana'));
});

it('personalul e redirecționat din cabinet spre panou — meniul lui e acolo', function () {
    $profesor = canteenUser(UserRole::Profesor);

    $this->actingAs($profesor)->get('/cabinet/meniu')->assertRedirect();
});

it('o salvare din panou se vede instant în cabinet — fără cache pe citire', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00', SchoolCalendar::TIMEZONE));

    $parinte = canteenUser(UserRole::Parinte);

    $this->actingAs($parinte)
        ->get('/cabinet/meniu')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('groups.0.days.2.menu', null));

    CanteenMenu::factory()->create(['menu_date' => '2026-09-09', 'breakfast_main' => 'Omletă clasică']);

    $this->actingAs($parinte)
        ->get('/cabinet/meniu')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.0.days.2.menu.breakfast.0.value', 'Omletă clasică'));
});
