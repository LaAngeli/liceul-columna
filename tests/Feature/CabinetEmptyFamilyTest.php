<?php

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Cont de familie FĂRĂ niciun elev vizibil — părintele înregistrat înaintea legării copilului
 * (legătura e opțională la creare), sau al cărui copil a plecat din școală.
 *
 * Sidebar-ul cabinetului e static: TOATE linkurile se randează pentru oricine ajunge acolo. Deci
 * fiecare trebuie să răspundă cu o pagină goală explicativă, nu cu 403 — un refuz pe un link pe care
 * chiar noi l-am afișat nu e securitate, e o fundătură.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/** @return list<array{0: string}> */
function cabinetSidebarRoutes(): array
{
    return [
        ['/dashboard'],
        ['/cabinet/note'],
        ['/cabinet/absente'],
        ['/cabinet/cronologie'],
        ['/cabinet/orar'],
        ['/cabinet/teme'],
        ['/cabinet/meniu'],
        ['/cabinet/documente'],
        ['/cabinet/calendar'],
        ['/cabinet/mesaje'],
        ['/cabinet/notificari'],
        ['/cabinet/notificari/setari'],
        ['/cabinet/profil'],
    ];
}

it('un părinte fără copii legați ajunge pe fiecare link din sidebar, nu în 403', function (string $uri) {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()])
        ->get($uri)
        ->assertOk();
})->with(cabinetSidebarRoutes());

it('calendarul fără copii se randează gol, fără să scurgă elevi străini', function () {
    // Un elev al ALTEI familii, cu activitate reală în luna curentă.
    $other = Student::factory()->create();
    Absence::factory()->create([
        'student_id' => $other->id,
        'occurred_on' => CarbonImmutable::now()->startOfMonth()->addDays(2),
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/cabinet/calendar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/calendar')
            ->where('children', [])
            // Structura anului (semestre/vacanțe) e informație globală non-PII și poate apărea;
            // ce NU are voie să apară e vreun eveniment produs de un proiector per-elev.
            ->where('events', fn (Collection $events): bool => $events
                ->pluck('source')
                ->intersect(['absence', 'homework', 'corigenta_exam', 'corigenta_session', 'motivation_deadline'])
                ->isEmpty())
        );
});

it('endpoint-ul JSON de evenimente răspunde gol în loc de 403', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()])
        ->getJson('/cabinet/calendar/events')
        ->assertOk()
        ->assertJsonStructure(['events']);
});
