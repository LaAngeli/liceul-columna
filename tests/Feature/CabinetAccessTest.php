<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('un părinte vede profilul copilului său, dar nu al altora', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $child = Student::factory()->create();
    $parent->students()->attach($child->id);
    $other = Student::factory()->create();

    $this->actingAs($parent)->get("/cabinet/elev/{$child->id}")->assertOk();
    $this->actingAs($parent)->get("/cabinet/elev/{$other->id}")->assertForbidden();
});

it('un elev își vede doar propriul profil', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $own = Student::factory()->create(['user_id' => $user->id]);
    $other = Student::factory()->create();

    $this->actingAs($user)->get("/cabinet/elev/{$own->id}")->assertOk();
    $this->actingAs($user)->get("/cabinet/elev/{$other->id}")->assertForbidden();
});

it('personalul poate vedea orice elev', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $student = Student::factory()->create();

    $this->actingAs($staff)->get("/cabinet/elev/{$student->id}")->assertOk();
});

it('redirecționează personalul de la cabinet (/dashboard) către panou (/admin)', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/admin');
})->with([UserRole::Admin, UserRole::Director, UserRole::DirectorAdjunct, UserRole::Diriginte, UserRole::Profesor]);

it('permite elevilor și părinților accesul la cabinet (/dashboard)', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/dashboard')->assertOk();
})->with([UserRole::Elev, UserRole::Parinte]);

it('homePath duce personalul la panou și restul la cabinet', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    expect($admin->homePath())->toBe('/admin');

    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);
    expect($elev->homePath())->toBe(route('dashboard'));
});

it('răspunsul de login și cel de 2FA folosesc implementările proiectului', function () {
    expect(app(LoginResponse::class))
        ->toBeInstanceOf(App\Http\Responses\LoginResponse::class)
        ->and(app(TwoFactorLoginResponse::class))
        ->toBeInstanceOf(App\Http\Responses\TwoFactorLoginResponse::class);
});
