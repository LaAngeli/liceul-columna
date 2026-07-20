<?php

/**
 * Paginile de REFERINȚĂ (LOT 8 al restructurării „Configurare"): regulile de notare și matricea de
 * roluri.
 *
 * Amândouă răspund aceleiași nevoi — a face vizibil ce era adevărat doar în cod — și amândouă au
 * aceeași disciplină: conținutul se DERIVĂ din sursa reală, nu se rescrie alături. Testele de aici
 * apără exact acea derivare: dacă cineva le-ar transforma în text fix, ele pică.
 */

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Filament\Pages\GradingRules;
use App\Filament\Pages\RoleMatrix;
use App\Models\User;
use App\Support\Grades;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function referenceUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    return $user;
}

it('regulile de notare se citesc din motorul de calcul, nu dintr-un text paralel', function () {
    referenceUser(UserRole::Diriginte);

    $page = Livewire::test(GradingRules::class)->instance();

    // Ponderea afișată vine din enum: schimbată acolo, se schimbă și pe pagină.
    $percent = (int) round((EvaluationType::Teza->weight() ?? 0.5) * 100);
    $gimnaziu = collect($page->cycleRules())->firstWhere('grades', 'V–IX');

    expect($gimnaziu['formula'])->toContain((string) $percent);

    // Exemplul de trunchiere e CALCULAT cu funcția reală — 8,567 → 8,56, nu 8,57.
    $truncation = collect($page->commonRules())->first();

    expect($truncation['body'])->toContain(number_format(Grades::truncate2(8.567), 2, ',', ''))
        ->and($truncation['body'])->not->toContain('8,57');

    // Pragul de promovare vine tot din constanta unică.
    $pass = collect($page->commonRules())->firstWhere('title', __('panel.grading_rules.pass_title'));

    expect($pass['body'])->toContain(number_format(Grades::PASS, 2, ',', ''));
});

it('ciclul primar apare fără sumativă, gimnaziul și liceul cu ea', function () {
    referenceUser(UserRole::Profesor);

    $rules = collect(Livewire::test(GradingRules::class)->instance()->cycleRules());

    expect($rules->firstWhere('grades', 'I–IV')['formula'])->toBe(__('panel.grading_rules.formula_primary'))
        ->and($rules->firstWhere('grades', 'X–XII')['formula'])->not->toBe(__('panel.grading_rules.formula_primary'));
});

it('regulile de notare sunt deschise personalului pedagogic, dar nu administratorului tehnic', function () {
    referenceUser(UserRole::Profesor);
    expect(GradingRules::canAccess())->toBeTrue();

    referenceUser(UserRole::Parinte);
    expect(GradingRules::canAccess())->toBeTrue();

    // Infrastructura rămâne în afara datelor academice (§3.2).
    referenceUser(UserRole::AdministratorTehnic);
    expect(GradingRules::canAccess())->toBeFalse();
});

it('matricea de roluri reflectă capabilitățile REALE, interogate pe rol', function () {
    referenceUser(UserRole::Admin);

    $matrix = collect(Livewire::test(RoleMatrix::class)->instance()->capabilities());

    $catalog = $matrix->firstWhere('name', 'canAdministerCatalog');

    expect($catalog)->not->toBeNull()
        // Exact repartiția din §3.3: conducerea academică scrie în catalog, AO nu.
        ->and($catalog['roles'][UserRole::Director->value])->toBeTrue()
        ->and($catalog['roles'][UserRole::PrimVicedirector->value])->toBeTrue()
        ->and($catalog['roles'][UserRole::AdministratorOperational->value])->toBeFalse()
        ->and($catalog['roles'][UserRole::Parinte->value])->toBeFalse();

    // Confirmarea că valorile chiar vin din model, nu dintr-o listă scrisă în pagină.
    $probe = User::factory()->create();
    $probe->assignRole(UserRole::AdministratorOperational->value);

    expect($catalog['roles'][UserRole::AdministratorOperational->value])
        ->toBe($probe->canAdministerCatalog());
});

it('matricea nu inventează coloane pentru capabilități care cer un context', function () {
    referenceUser(UserRole::Admin);

    $names = collect(Livewire::test(RoleMatrix::class)->instance()->capabilities())->pluck('name');

    // `canAccessPanel(Panel)` depinde de un argument pe care matricea nu-l are: a-l chema cu o
    // valoare inventată ar produce o coloană care arată sigură și e falsă.
    expect($names)->not->toContain('canAccessPanel')
        // …dar capabilitățile fără parametri sunt toate acolo.
        ->and($names)->toContain('canConfigureSchool', 'canManageAccounts', 'canViewSchedules');
});

it('matricea nu creează conturi ca efect secundar al afișării', function () {
    referenceUser(UserRole::Admin);

    $before = User::query()->count();

    Livewire::test(RoleMatrix::class)->instance()->capabilities();

    expect(User::query()->count())->toBe($before);
});

it('matricea de roluri e închisă cui nu administrează conturi și nu auditează', function () {
    referenceUser(UserRole::Profesor);
    expect(RoleMatrix::canAccess())->toBeFalse();

    referenceUser(UserRole::Parinte);
    expect(RoleMatrix::canAccess())->toBeFalse();

    referenceUser(UserRole::Director);
    expect(RoleMatrix::canAccess())->toBeTrue();
});

it('DISCIPLINĂ: fiecare capabilitate din matrice are etichetă tradusă în toate limbile', function () {
    referenceUser(UserRole::Admin);

    $capabilities = collect(Livewire::test(RoleMatrix::class)->instance()->capabilities())->pluck('name');
    $missing = [];

    foreach (['ro', 'ru', 'en'] as $locale) {
        foreach ($capabilities as $name) {
            $key = 'panel.role_matrix.capabilities.'.$name;

            if (__($key, [], $locale) === $key) {
                $missing[] = "{$locale}: {$name}";
            }
        }
    }

    // Pagina afișează lizibil și capabilitățile netraduse (nume descompus în cuvinte), tocmai ca o
    // capabilitate NOUĂ să nu dispară din tabel. Dar netradusă e o stare provizorie, nu una
    // acceptabilă: fără testul acesta, „Can manage documents" ar fi rămas așa la nesfârșit.
    expect($missing)->toBe([]);
});

it('capabilitățile fără niciun punct de aplicare nu mai există în model', function () {
    // Ambele existau declarate, dar nu gardau nimic: un drept promis fără loc de verificare e mai
    // rău decât absența lui, fiindcă cine citește codul presupune că se aplică undeva.
    expect(method_exists(User::class, 'canChangeAveragingFormula'))->toBeFalse()
        ->and(method_exists(User::class, 'canHandleAudiences'))->toBeFalse();
});
