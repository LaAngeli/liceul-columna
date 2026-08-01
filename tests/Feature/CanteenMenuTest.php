<?php

/**
 * Meniul cantinei (cerință 2026-08-01): scrierea aparține EXCLUSIV administratorului operațional
 * (super-adminul păstrează break-glass), consultarea e a toată platforma — personalul în panou,
 * familia în cabinet. Fără cache: o salvare din panou se vede în cabinet la următorul request.
 */

use App\Enums\UserRole;
use App\Filament\Resources\CanteenMenus\Pages\CreateCanteenMenu;
use App\Filament\Resources\CanteenMenus\Pages\ListCanteenMenus;
use App\Models\CanteenMenu;
use App\Models\User;
use App\Support\SchoolCalendar;
use Filament\Actions\Testing\TestAction;
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

    // Toți cei din panou pot consulta lista.
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
    $menu = CanteenMenu::factory()->create();
    $this->actingAs($director)->get("/admin/canteen-menus/{$menu->id}/edit")->assertForbidden();
    $this->actingAs($ao)->get("/admin/canteen-menus/{$menu->id}/edit")->assertOk();

    // Afordanțele din listă spun adevărul: cititorul nu vede „Editare"/„Duplică" (evaluare
    // directă pe instanță — assertTableAction* e nedeterminist pe acțiuni de rând).
    $this->actingAs($profesor);
    $readerTable = Livewire::test(ListCanteenMenus::class)->instance()->getTable();

    expect($readerTable->getAction('edit')->record($menu)->isVisible())->toBeFalse()
        ->and($readerTable->getAction('duplicate')->record($menu)->isVisible())->toBeFalse()
        ->and($readerTable->getAction('preview')->record($menu)->isVisible())->toBeTrue();

    $this->actingAs($ao);
    $managerTable = Livewire::test(ListCanteenMenus::class)->instance()->getTable();

    expect($managerTable->getAction('edit')->record($menu)->isVisible())->toBeTrue()
        ->and($managerTable->getAction('duplicate')->record($menu)->isVisible())->toBeTrue();
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

it('duplicarea copiază rubricile pe altă dată; data ocupată e refuzată', function () {
    $this->actingAs(canteenUser(UserRole::AdministratorOperational));

    $menu = CanteenMenu::factory()->create(['menu_date' => '2026-09-07']);
    CanteenMenu::factory()->create(['menu_date' => '2026-09-08']);

    Livewire::test(ListCanteenMenus::class)
        ->callAction(TestAction::make('duplicate')->table($menu), ['target_date' => '2026-09-14'])
        ->assertHasNoErrors();

    $copy = CanteenMenu::query()->whereDate('menu_date', '2026-09-14')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->lunch_second)->toBe($menu->lunch_second);

    // Ținta deja ocupată → eroare de formular pe acțiune.
    Livewire::test(ListCanteenMenus::class)
        ->callAction(TestAction::make('duplicate')->table($menu), ['target_date' => '2026-09-08'])
        ->assertHasFormErrors(['target_date']);
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
            ->where('week.isCurrent', true)
            // Luni–vineri prezente chiar fără meniu; miercuri poartă datele publicate.
            ->has('days', 5)
            ->where('days.2.isToday', true)
            ->where('days.2.menu.lunch.1.value', 'Gulaș din carne de porc cu legume înăbușite')
            // Rubricile necompletate NU se trimit — clientul nu afișează rânduri goale.
            ->where('days.2.menu.lunch', fn ($rows) => collect($rows)->pluck('label')->doesntContain(__('panel.forms.canteen.lunch_side')))
            ->where('days.0.menu', null));

    // Navigarea pe altă săptămână: ancora mută intervalul, ziua rămâne marcată doar azi.
    $this->actingAs($parinte)
        ->get('/cabinet/meniu?data=2026-09-16')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('week.isCurrent', false)
            ->where('days.2.isToday', false));

    // Ancoră coruptă → cade pe azi, nu pe excepție.
    $this->actingAs($parinte)->get('/cabinet/meniu?data=abc')->assertOk();
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
        ->assertInertia(fn (AssertableInertia $page) => $page->where('days.2.menu', null));

    CanteenMenu::factory()->create(['menu_date' => '2026-09-09', 'breakfast_main' => 'Omletă clasică']);

    $this->actingAs($parinte)
        ->get('/cabinet/meniu')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('days.2.menu.breakfast.0.value', 'Omletă clasică'));
});
