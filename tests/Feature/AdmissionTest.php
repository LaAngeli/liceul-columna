<?php

use App\Enums\UserRole;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use App\Models\AdmissionRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('afișează formularul public de înscriere', function () {
    $this->get('/inregistrarea-student')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/admitere/inregistrare'));
});

it('salvează o cerere de înscriere validă', function () {
    $this->post('/inregistrarea-student', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'email' => 'maria@example.com',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
        'preferred_time' => 'Luni, după-amiaza',
    ])->assertRedirect();

    $this->assertDatabaseHas('admission_requests', [
        'child_name' => 'Ion Popescu',
        'parent_name' => 'Maria Popescu',
        'status' => 'nou',
    ]);
});

it('respinge cererea fără câmpurile obligatorii', function () {
    $this->post('/inregistrarea-student', ['parent_name' => ''])
        ->assertSessionHasErrors(['parent_name', 'phone', 'child_name']);

    expect(AdmissionRequest::count())->toBe(0);
});

it('doar administrația academică vede cererile de înscriere în panou', function (UserRole $role, bool $access) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $this->actingAs($user);

    expect(AdmissionRequestResource::canAccess())->toBe($access);
})->with([
    'super-admin' => [UserRole::Admin, true],
    'director' => [UserRole::Director, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector, true],
    'administrator operațional' => [UserRole::AdministratorOperational, true],
    'administrator tehnic' => [UserRole::AdministratorTehnic, false],
    'profesor' => [UserRole::Profesor, false],
    'diriginte' => [UserRole::Diriginte, false],
    'părinte' => [UserRole::Parinte, false],
]);
