<?php

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('permite autentificarea cu email', function () {
    $user = User::factory()->create(['email' => 'maria@scoala.test', 'password' => 'parola-veche']);

    $this->post('/login', ['email' => 'maria@scoala.test', 'password' => 'parola-veche']);

    $this->assertAuthenticatedAs($user);
});

it('permite autentificarea cu username (userii migrați din vechiul sistem)', function () {
    $user = User::factory()->create(['username' => 'mariap', 'email' => null, 'password' => 'parola-veche']);

    $this->post('/login', ['email' => 'mariap', 'password' => 'parola-veche']);

    $this->assertAuthenticatedAs($user);
});

it('autentificarea pe username e insensibilă la majuscule', function () {
    $user = User::factory()->create(['username' => 'mariap', 'email' => null, 'password' => 'parola-veche']);

    $this->post('/login', ['email' => 'MariaP', 'password' => 'parola-veche']);

    $this->assertAuthenticatedAs($user);
});

it('respinge parola greșită', function () {
    User::factory()->create(['username' => 'mariap', 'password' => 'parola-veche']);

    $this->post('/login', ['email' => 'mariap', 'password' => 'gresit']);

    $this->assertGuest();
});
