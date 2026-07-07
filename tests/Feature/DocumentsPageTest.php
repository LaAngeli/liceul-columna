<?php

use App\Enums\UserRole;
use App\Filament\Pages\Documents;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Categorie „Documente" — placeholder gol, cerut explicit ca secțiune vizibilă în sidebar
 * înainte de a exista conținut real. Verifică doar existența/randarea paginii + gruparea.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('pagina Documente se randează pentru personal și arată starea de „în pregătire"', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Director->value);

    $this->actingAs($staff);

    Livewire::test(Documents::class)
        ->assertOk()
        ->assertSee(__('panel.pages.documents.empty_heading'));
});

it('pagina Documente e înregistrată sub grupul de navigare „Documente"', function () {
    expect(Documents::getNavigationGroup())->toBe(__('panel.nav.groups.documents'));
});
