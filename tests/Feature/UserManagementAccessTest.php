<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('permite doar adminului gestiunea utilizatorilor (creare profile)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    $this->actingAs($admin);

    expect(UserResource::canAccess())->toBeTrue();
});

it('refuză personalului non-admin gestiunea utilizatorilor', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user);

    expect(UserResource::canAccess())->toBeFalse();
})->with([UserRole::Director, UserRole::Profesor, UserRole::Diriginte]);
