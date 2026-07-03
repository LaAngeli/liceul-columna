<?php

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('trimite oaspeții de la /studio către login-ul propriu', function () {
    $response = $this->get('/studio');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/studio/login');
});

it('contul unic de conținut accesează /studio', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get('/studio')->assertOk();
});

it('marchează panoul de conținut cu noindex', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/studio')
        ->assertOk()
        ->assertSee('noindex', escape: false);
});

it('personalul academic (guard web) NU poate accesa /studio', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Admin->value);

    $response = $this->actingAs($staff)->get('/studio');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/studio/login');
});

it('contul de conținut NU poate accesa panoul academic /admin', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
});

it('sesiunea contului de conținut NU se scurge în guard-ul web (cabinet/academic)', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin');

    // Autentificat pe guard-ul izolat `admin`, dar NU pe `web` (cabinetul + /admin folosesc `web`).
    expect(auth('admin')->check())->toBeTrue();
    expect(auth('web')->check())->toBeFalse();
});

it('panoul de conținut nu expune înregistrare', function () {
    $this->get('/studio/register')->assertNotFound();
});

it('comanda app:cms-admin provizionează idempotent contul unic', function () {
    $this->artisan('app:cms-admin', [
        '--email' => 'redactor@columna.md',
        '--password' => 'parola-super-secreta-123',
    ])->assertSuccessful();

    // A doua rulare actualizează, nu duplică; fără parolă, o păstrează pe cea existentă.
    $this->artisan('app:cms-admin', [
        '--email' => 'redactor@columna.md',
        '--name' => 'Redacția Columna',
    ])->assertSuccessful();

    expect(Admin::where('email', 'redactor@columna.md')->count())->toBe(1);
    expect(Admin::where('email', 'redactor@columna.md')->value('name'))->toBe('Redacția Columna');
});
