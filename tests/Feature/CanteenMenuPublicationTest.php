<?php

/**
 * PUBLICAREA AMÂNATĂ a meniului cantinei (cerința beneficiarului, 04.08.2026): meniul se introduce
 * oricând, dar apare tuturor abia pe 25 ale lunii precedente, iar luna încheiată se retrage singură.
 *
 * Testele fixează exact granițele calendaristice (24 vs 25, ultima zi a lunii vs prima zi a celei
 * noi) și cine vede ce — acolo se poate strica tăcut, iar greșeala s-ar vedea abia peste o lună.
 */

use App\Enums\UserRole;
use App\Filament\Resources\CanteenMenus\Pages\CanteenMenuHistory;
use App\Models\CanteenMenu;
use App\Models\User;
use App\Support\CanteenWeek;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Trei luni: cea încheiată, cea în curs, cea care urmează.
    foreach (['2026-04-15', '2026-05-15', '2026-06-15'] as $date) {
        CanteenMenu::factory()->create(['menu_date' => $date, 'lunch_first' => 'Supă '.$date]);
    }

    $this->ao = User::factory()->create();
    $this->ao->assignRole(UserRole::AdministratorOperational->value);

    $this->parent = User::factory()->create();
    $this->parent->assignRole(UserRole::Parinte->value);

    $this->teacher = User::factory()->create();
    $this->teacher->assignRole(UserRole::Profesor->value);
});

afterEach(fn () => Carbon::setTestNow());

/** Zilele cu meniu pe care le VEDE privitorul curent, la data dată. */
function visibleMenuDates(string $today): array
{
    Carbon::setTestNow(Carbon::parse($today.' 09:00:00'));

    return CanteenMenu::query()->visible()->orderBy('menu_date')->pluck('menu_date')
        ->map(fn ($date): string => $date->toDateString())
        ->all();
}

it('meniul lunii următoare NU apare înainte de 25 — și apare fix pe 25', function () {
    actingAs($this->parent);

    // 24 mai: doar mai. Iunie e scris, dar încă nu e treaba nimănui.
    expect(visibleMenuDates('2026-05-24'))->toBe(['2026-05-15']);

    // 25 mai: iunie intră în fereastră, iar mai RĂMÂNE — mai sunt zile de mâncat în el.
    expect(visibleMenuDates('2026-05-25'))->toBe(['2026-05-15', '2026-06-15']);
});

it('luna încheiată se retrage singură pe 1 ale lunii noi', function () {
    actingAs($this->parent);

    // 31 mai: încă se vede mai (ultima lui zi).
    expect(visibleMenuDates('2026-05-31'))->toBe(['2026-05-15', '2026-06-15']);

    // 1 iunie: mai a ieșit din vederi, aprilie de mult.
    expect(visibleMenuDates('2026-06-01'))->toBe(['2026-06-15']);
});

it('administratorul operațional vede tot — și nepublicatul, și arhiva', function () {
    actingAs($this->ao);

    expect(visibleMenuDates('2026-05-24'))->toBe(['2026-04-15', '2026-05-15', '2026-06-15']);
});

it('profesorul NU e autor: vede doar fereastra publică', function () {
    actingAs($this->teacher);

    expect(visibleMenuDates('2026-05-24'))->toBe(['2026-05-15']);
});

it('regula trăiește în axa comună, deci cabinetul și panoul văd la fel', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-24 09:00:00'));
    actingAs($this->parent);

    // Aceeași metodă alimentează planificatorul din panou și pagina din cabinet.
    $june = CanteenWeek::period('luna', '2026-06-15', null, null);
    $days = collect($june['groups'])->flatMap(fn (array $group): array => $group['days']);

    expect($days->firstWhere('date', '2026-06-15')['menu'])->toBeNull();

    // Aceeași zi, privită de AO: meniul e acolo (el îl pregătește).
    actingAs($this->ao);
    $juneForAo = CanteenWeek::period('luna', '2026-06-15', null, null);
    $daysForAo = collect($juneForAo['groups'])->flatMap(fn (array $group): array => $group['days']);

    expect($daysForAo->firstWhere('date', '2026-06-15')['menu'])->not->toBeNull();
});

it('marchează în panou meniul încă nepublicat, cu data publicării', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-24 09:00:00'));

    $june = CanteenMenu::query()->whereDate('menu_date', '2026-06-15')->sole();
    $may = CanteenMenu::query()->whereDate('menu_date', '2026-05-15')->sole();

    expect($june->isPublished())->toBeFalse()
        ->and($june->publishedOn()->toDateString())->toBe('2026-05-25')
        ->and($may->isPublished())->toBeTrue();
});

// ─── Istoricul (AO) ──────────────────────────────────────────────────────────────────────

it('istoricul se deschide pe ultima lună ÎNCHEIATĂ și o arată ca tabel lunar', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-24 09:00:00'));
    actingAs($this->ao);

    $page = Livewire::test(CanteenMenuHistory::class)->instance();

    // Aprilie: luna care tocmai a ieșit din vederile curente — motivul pentru care intri aici.
    expect($page->activeMonth())->toBe('2026-04')
        ->and(array_column($page->months(), 'state'))->toBe(['upcoming', 'current', 'archived']);

    $rows = $page->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['date'])->toBe('15.04.2026')
        // Cele zece rubrici ale meniului tipărit: 4 la dejun, 6 la prânz.
        ->and($rows[0]['breakfast'])->toHaveCount(4)
        ->and($rows[0]['lunch'])->toHaveCount(6);

    // O lună aleasă explicit se deschide ca atare.
    expect(Livewire::test(CanteenMenuHistory::class)->call('openMonth', '2026-06')->instance()->activeMonth())
        ->toBe('2026-06');
});

it('istoricul e închis cui nu scrie meniul', function () {
    actingAs($this->ao);
    expect(CanteenMenuHistory::canAccess())->toBeTrue();

    foreach ([$this->teacher, $this->parent] as $user) {
        actingAs($user->fresh());
        expect(CanteenMenuHistory::canAccess())->toBeFalse();
    }
});
