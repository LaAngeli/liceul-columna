<?php

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

test('personalul este redirecționat către panou după logare', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin');
})->with([UserRole::Admin, UserRole::Director, UserRole::Profesor, UserRole::Diriginte]);

test('elevii și părinții sunt redirecționați către cabinet după logare', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
})->with([UserRole::Elev, UserRole::Parinte]);

test('autentificarea separată /admin/login nu mai există', function () {
    $this->get('/admin/login')->assertNotFound();
});

test('logarea Inertia a personalului forțează navigare completă (Inertia::location) către panou', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Admin->value);

    // Formularul public de login trimite header-ul X-Inertia → ținta /admin (Filament,
    // non-Inertia) trebuie servită prin Inertia::location (409 + X-Inertia-Location).
    $response = $this->withHeader('X-Inertia', 'true')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($response->getStatusCode())->toBe(409)
        ->and($response->headers->get('X-Inertia-Location'))->toContain('/admin');
});

test('logarea Inertia a unui elev redirecționează normal către cabinet', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);

    $this->withHeader('X-Inertia', 'true')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('logarea ignoră un URL „intended" inaccesibil rolului (după delogarea altui rol)', function () {
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);

    // Simulează un „intended" = /admin rămas în sesiune după delogarea unui admin.
    $this->withSession(['url.intended' => '/admin'])
        ->post('/login', ['email' => $elev->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});
