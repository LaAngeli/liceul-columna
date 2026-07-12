<?php

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Modul Calendar C4: endpointul de cabinet livrează feed-ul agregat scoped pe familie. Acoperă
 * accesul familiei, izolarea cross-family prin parametrul `student` și interdicția pentru staff.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function parentWithChild(): array
{
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $child = Student::factory()->create();
    $parent->students()->attach($child->id);

    return [$parent, $child];
}

it('părintele vede pagina de calendar cu evenimentele copilului', function () {
    $this->withoutVite();
    // Pagina React e construită în C5; aici verificăm doar props-urile backend.
    config(['inertia.testing.ensure_pages_exist' => false]);

    [$parent, $child] = parentWithChild();

    Absence::factory()->for($child)->create([
        'occurred_on' => now()->startOfMonth()->addDays(5)->toDateString(),
        'is_motivated' => false,
    ]);

    $this->actingAs($parent)
        ->get(route('cabinet.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/calendar')
            ->has('events')
            ->has('children', 1)
            ->where('children.0.id', $child->id));
});

it('endpointul de evenimente întoarce JSON', function () {
    [$parent, $child] = parentWithChild();

    Absence::factory()->for($child)->create([
        'occurred_on' => now()->startOfMonth()->addDays(3)->toDateString(),
        'is_motivated' => false,
    ]);

    $events = $this->actingAs($parent)
        ->getJson(route('cabinet.calendar.events'))
        ->assertOk()
        ->json('events');

    expect(collect($events)->pluck('source'))->toContain('absence');
});

it('părintele nu vede evenimentele altui copil prin parametrul student', function () {
    [$parent] = parentWithChild();

    $otherChild = Student::factory()->create();
    Absence::factory()->for($otherChild)->create([
        'occurred_on' => now()->startOfMonth()->addDays(4)->toDateString(),
        'is_motivated' => false,
    ]);

    $events = $this->actingAs($parent)
        ->getJson(route('cabinet.calendar.events', ['student' => $otherChild->id]))
        ->assertOk()
        ->json('events');

    expect(collect($events)->pluck('source'))->not->toContain('absence');
});

it('personalul e redirecționat de la calendarul de cabinet (EnsureFamilyCabinet, #37)', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::Profesor->value);

    // Gating UNIFORM cu restul cabinetului: personalul → /admin (nu 403).
    $this->actingAs($staff)
        ->get(route('cabinet.calendar'))
        ->assertRedirect();
});

it('titlurile auto ale calendarului respectă limba familiei (SetUserLocale, #37)', function () {
    $this->withoutVite();

    $parent = User::factory()->create(['locale' => 'ru']);
    $parent->assignRole(UserRole::Parinte->value);
    $child = Student::factory()->create();
    $parent->students()->attach($child->id);

    // Un semestru care începe în luna curentă → titlul „Început de semestru" apare în feed.
    Term::factory()->create([
        'starts_on' => now()->startOfMonth()->addDays(2)->toDateString(),
        'ends_on' => now()->endOfMonth()->toDateString(),
    ]);

    $response = $this->actingAs($parent)->getJson(route('cabinet.calendar.events'));
    $titles = collect($response->json('events'))->pluck('title');

    // Fără SetUserLocale, titlul ar fi în RO ('Început de semestru').
    expect($titles)->toContain(trans('cabinet_calendar.auto_term_start', [], 'ru'))
        ->and($titles)->not->toContain(trans('cabinet_calendar.auto_term_start', [], 'ro'));
});
