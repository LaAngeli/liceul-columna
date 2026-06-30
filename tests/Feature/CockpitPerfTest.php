<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Plafon de performanță pentru cockpit (audit § perf cockpit #1): un părinte cu 3 copii NU trebuie să
 * genereze mai mult de un număr rezonabil de query-uri. Înainte de fix: ~70 (currentStatus calculat de 2×
 * per copil + ComputeStudentDynamics::for() integral pentru `trend` + lastGrade per copil).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    // /dashboard randează cu @vite — fără manifest stabil testul ar pica intermittent.
    $this->withoutVite();
});

it('cockpit-ul nu generează mai mult de 25 query-uri pentru un părinte cu 3 copii', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $children = Student::factory()->count(3)->create();
    $parent->students()->attach($children->pluck('id'));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($parent)->get('/dashboard')->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThan(25, "Cockpit a generat {$count} query-uri (plafon: 25).");
});
