<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('permite personalului școlii accesul la panoul de gestiune', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
})->with(UserRole::panelRoles());

it('refuză elevilor și părinților accesul la panoul de gestiune', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
})->with([UserRole::Elev, UserRole::Parinte]);

it('refuză accesul unui utilizator fără rol', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});
