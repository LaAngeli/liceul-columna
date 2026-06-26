<?php

use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesManageableRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/**
 * Mic harness care expune garda de rol din trait-ul folosit de paginile Create/Edit.
 */
function roleGuardHarness(): object
{
    return new class
    {
        use EnforcesManageableRole;

        public ?User $record = null;

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function guard(array $data): array
        {
            return $this->pullAndGuardRole($data);
        }

        public function role(): ?string
        {
            return $this->selectedRole;
        }
    };
}

function actingAsRole(UserRole $role): void
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    test()->actingAs($user);
}

it('directorul NU poate atribui rolul admin (gardă pe server)', function () {
    actingAsRole(UserRole::Director);

    roleGuardHarness()->guard(['name' => 'X', 'role' => UserRole::Admin->value]);
})->throws(ValidationException::class);

it('administratorul operațional nu poate atribui roluri de conducere (doar familie + personal)', function () {
    actingAsRole(UserRole::AdministratorOperational);

    expect(fn () => roleGuardHarness()->guard(['role' => UserRole::Admin->value]))
        ->toThrow(ValidationException::class)
        ->and(fn () => roleGuardHarness()->guard(['role' => UserRole::Director->value]))
        ->toThrow(ValidationException::class);
});

it('prim-vicedirectorul nu poate atribui niciun rol (nu gestionează conturi)', function () {
    actingAsRole(UserRole::PrimVicedirector);

    roleGuardHarness()->guard(['role' => UserRole::Profesor->value]);
})->throws(ValidationException::class);

it('un rol permis trece, iar `role` e scos din datele modelului', function () {
    actingAsRole(UserRole::Director);

    $harness = roleGuardHarness();
    $data = $harness->guard(['name' => 'X', 'role' => UserRole::Profesor->value]);

    expect($data)->not->toHaveKey('role')
        ->and($data)->toHaveKey('name')
        ->and($harness->role())->toBe(UserRole::Profesor->value);
});

it('rolul lipsă e respins', function () {
    actingAsRole(UserRole::Admin);

    roleGuardHarness()->guard(['name' => 'X']);
})->throws(ValidationException::class);
