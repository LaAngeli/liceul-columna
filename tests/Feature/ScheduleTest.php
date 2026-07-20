<?php

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Widgets\SchedulesToComplete;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('citirea publică întoarce DOAR orarele publicate, în forma whitelist (label/headers/rows)', function () {
    Schedule::factory()->ofType(ScheduleType::Bells)->create([
        'label' => 'Sunete',
        'headers' => ['Lecția', 'Interval'],
        'rows' => [['Lecția 1', '08.00 – 08.45']],
        'is_public' => true,
    ]);
    Schedule::factory()->ofType(ScheduleType::Bells)->internal()->create(['label' => 'Draft intern']);

    $tables = Schedule::publicTablesFor(ScheduleType::Bells->value);

    expect($tables)->toHaveCount(1)
        ->and(array_keys($tables[0]))->toBe(['label', 'headers', 'rows'])
        ->and($tables[0]['label'])->toBe('Sunete')
        ->and($tables[0]['headers'])->toBe(['Lecția', 'Interval'])
        ->and($tables[0]['rows'][0])->toBe(['Lecția 1', '08.00 – 08.45']);
});

it('editarea în panou invalidează cache-ul public (sursă unică reflectată pe site)', function () {
    $schedule = Schedule::factory()->ofType(ScheduleType::Exams)->create(['label' => 'Vechi', 'is_public' => true]);

    expect(Schedule::publicTablesFor(ScheduleType::Exams->value)[0]['label'])->toBe('Vechi');

    $schedule->update(['label' => 'Nou']);

    expect(Schedule::publicTablesFor(ScheduleType::Exams->value)[0]['label'])->toBe('Nou');
});

it('depublicarea (is_public=false) ascunde orarul de pe site', function () {
    $schedule = Schedule::factory()->ofType(ScheduleType::Recovery)->create(['is_public' => true]);
    expect(Schedule::publicTablesFor(ScheduleType::Recovery->value))->toHaveCount(1);

    $schedule->update(['is_public' => false]);
    expect(Schedule::publicTablesFor(ScheduleType::Recovery->value))->toHaveCount(0);
});

it('pagina publică de orar randează tabelele din DB', function () {
    Schedule::factory()->ofType(ScheduleType::Lessons)->create([
        'label' => 'Clasa V demo',
        'headers' => ['', 'Luni'],
        'rows' => [['Lecția 1', 'Matematică']],
        'is_public' => true,
    ]);

    $this->get('/orarul-lectiilor')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/page')
            ->where('sections.1.label', 'Clasa V demo'));
});

/*
 * REGULĂ RESCRISĂ DELIBERAT (LOT 5): până acum, o singură capabilitate (`canManageSchedules`)
 * gardona și citirea, și scrierea — deci orarul era invizibil în panou conducerii și personalului
 * pedagogic, contrar §3.3 (rândul „Orar": Drg/VD/Dir/AO ●, Prof ◐). Acum VEDEREA are capabilitatea
 * ei; INSERAREA rămâne exact unde era.
 */
it('orarul se VEDE de conducere și de personalul pedagogic (§3.3), nu doar de cine îl inserează', function (UserRole $role, bool $access) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $this->actingAs($user);

    expect(ScheduleResource::canAccess())->toBe($access);
})->with([
    'super-admin' => [UserRole::Admin, true],
    'administrator operațional' => [UserRole::AdministratorOperational, true],
    'director' => [UserRole::Director, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector, true],
    'diriginte' => [UserRole::Diriginte, true],
    'profesor' => [UserRole::Profesor, true],
    // Infrastructură, fără date academice (§3.2).
    'administrator tehnic' => [UserRole::AdministratorTehnic, false],
    // Familia își vede orarul în cabinet, nu în panou.
    'părinte' => [UserRole::Parinte, false],
]);

it('INSERAREA orarelor rămâne obligația administratorului operațional (+ super-admin break-glass)', function (UserRole $role, bool $canWrite) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $this->actingAs($user);

    expect(ScheduleResource::canCreate())->toBe($canWrite);
})->with([
    'super-admin' => [UserRole::Admin, true],
    'administrator operațional' => [UserRole::AdministratorOperational, true],
    // Văd, dar nu scriu — publicarea rămâne act operațional.
    'director' => [UserRole::Director, false],
    'prim-vicedirector' => [UserRole::PrimVicedirector, false],
    'diriginte' => [UserRole::Diriginte, false],
    'profesor' => [UserRole::Profesor, false],
]);

it('widget-ul „orare de completat" apare AO-ului cât timp lipsesc tipuri și se ascunde când toate au date', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $this->actingAs($ao);

    expect(SchedulesToComplete::canView())->toBeTrue();

    foreach (ScheduleType::cases() as $type) {
        Schedule::factory()->ofType($type)->create(['is_public' => true]);
    }

    // Widget-ul memoizează rezultatul per-request — în test schimbăm starea în același proces,
    // deci golim cache-ul ca să recalculeze. În prod fiecare cerere are instanță proaspătă.
    SchedulesToComplete::flushCache();

    expect(SchedulesToComplete::canView())->toBeFalse();
});

it('widget-ul „orare de completat" NU apare directorului sau profesorului (obligația e a AO)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);
    expect(SchedulesToComplete::canView())->toBeFalse();

    $prof = User::factory()->create();
    $prof->assignRole(UserRole::Profesor->value);
    $this->actingAs($prof);
    expect(SchedulesToComplete::canView())->toBeFalse();
});
