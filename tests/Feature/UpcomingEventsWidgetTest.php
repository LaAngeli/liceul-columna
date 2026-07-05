<?php

use App\Enums\UserRole;
use App\Filament\Widgets\UpcomingEvents;
use App\Models\CalendarEvent;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function upcomingStaffUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function upcomingViewData(): array
{
    $widget = new UpcomingEvents;
    $method = new ReflectionMethod(UpcomingEvents::class, 'getViewData');
    $method->setAccessible(true);

    /** @var array<string, mixed> $data */
    $data = $method->invoke($widget);

    return $data;
}

it('e vizibil oricărui membru al staff-ului logat, ascuns musafirului', function () {
    $this->actingAs(upcomingStaffUser());
    expect(UpcomingEvents::canView())->toBeTrue();

    auth('web')->logout();
    expect(UpcomingEvents::canView())->toBeFalse();
});

it('afișează doar evenimentele viitoare, ordonate crescător după dată (exclude trecutul)', function () {
    CalendarEvent::factory()->create(['starts_on' => today()->addDays(3), 'title' => 'Peste 3 zile']);
    CalendarEvent::factory()->create(['starts_on' => today()->addDay(), 'title' => 'Mâine']);
    CalendarEvent::factory()->create(['starts_on' => today()->subDay(), 'title' => 'Ieri']);

    $this->actingAs(upcomingStaffUser());
    $events = upcomingViewData()['events'];

    expect($events)->toHaveCount(2)
        ->and($events[0]['title'])->toBe('Mâine')
        ->and($events[1]['title'])->toBe('Peste 3 zile')
        ->and(collect($events)->pluck('title'))->not->toContain('Ieri');
});

it('limitează la 5 evenimente', function () {
    CalendarEvent::factory()->count(7)->create(['starts_on' => today()->addDays(2)]);

    $this->actingAs(upcomingStaffUser());

    expect(upcomingViewData()['events'])->toHaveCount(5);
});

it('întoarce listă goală când nu sunt evenimente viitoare (empty state)', function () {
    CalendarEvent::factory()->create(['starts_on' => today()->subDays(5)]);

    $this->actingAs(upcomingStaffUser());

    expect(upcomingViewData()['events'])->toBe([]);
});

it('randează secțiunea „Evenimente apropiate" (smoke)', function () {
    CalendarEvent::factory()->create(['starts_on' => today()->addDay(), 'title' => 'Ședință cu părinții']);

    $this->actingAs(upcomingStaffUser());

    Livewire\Livewire::test(UpcomingEvents::class)
        ->assertOk()
        ->assertSee('Evenimente apropiate')
        ->assertSee('Ședință cu părinții');
});
