<?php

/**
 * RESPONSABILII DE DOMENIU — desemnarea, mutată de pe fișa persoanei la nivel de ȘCOALĂ
 * (cerința beneficiarului, 06.08.2026: „nu înțeleg necesitatea ferestrei «Domenii de audiență»").
 *
 * Era o bifă pe OM, dar întrebarea e a școlii: „cine răspunde de Instruire" și „cine de Educație".
 * De pe fișa unei persoane nu vezi dacă domeniul mai are un responsabil, dacă a rămas descoperit
 * sau dacă tocmai ai creat al treilea — și chiar așa arătau datele: 1 desemnare din 5 eligibili.
 *
 * Testele apără ce atârnă de desemnare (rutarea audiențelor + aprobarea motivărilor TARDIVE) și
 * invariantul nou: UN singur responsabil per domeniu.
 */

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Filament\Pages\AudienceDomainOwners;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create(['name' => 'Administrator']);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->vicedirector = User::factory()->create(['name' => 'Vicedirector Unu']);
    $this->vicedirector->assignRole(UserRole::PrimVicedirector->value);

    $this->operational = User::factory()->create(['name' => 'Operational Doi']);
    $this->operational->assignRole(UserRole::AdministratorOperational->value);

    // Un profesor: NU poate ține un domeniu (§4.2 — doar conducerea academică).
    $this->profesor = User::factory()->create(['name' => 'Profesor Trei']);
    $this->profesor->assignRole(UserRole::Profesor->value);

    actingAs($this->admin->fresh());
});

/** Starea unui domeniu, așa cum o raportează pagina. */
function domainState(string $domain): array
{
    $domenii = Livewire::test(AudienceDomainOwners::class)->instance()->domains();

    return collect($domenii)->firstWhere('value', $domain);
}

it('un domeniu fără responsabil se raportează ca DESCOPERIT, nu ca eroare', function () {
    $stare = domainState(AudienceDomain::Instruire->value);

    expect($stare['state'])->toBe('uncovered')
        ->and($stare['owners'])->toBe([]);
});

it('desemnarea atribuie domeniul și îl arată ca acoperit', function () {
    Livewire::test(AudienceDomainOwners::class)
        ->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Instruire->value]);

    $stare = domainState(AudienceDomain::Instruire->value);

    expect($stare['state'])->toBe('covered')
        ->and($stare['owners'])->toHaveCount(1)
        ->and($stare['owners'][0]['name'])->toBe('Vicedirector Unu')
        ->and($this->vicedirector->fresh()->handlesAudienceDomain(AudienceDomain::Instruire))->toBeTrue();
});

it('o a doua desemnare îl ÎNLOCUIEȘTE pe primul — un singur responsabil per domeniu', function () {
    $pagina = Livewire::test(AudienceDomainOwners::class);

    $pagina->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Instruire->value]);
    $pagina->callAction('assign', ['user_id' => $this->operational->id], arguments: ['domain' => AudienceDomain::Instruire->value]);

    $stare = domainState(AudienceDomain::Instruire->value);

    expect($stare['owners'])->toHaveCount(1)
        ->and($stare['owners'][0]['name'])->toBe('Operational Doi')
        // Primul chiar a pierdut domeniul, nu doar a coborât în listă.
        ->and($this->vicedirector->fresh()->handlesAudienceDomain(AudienceDomain::Instruire))->toBeFalse();
});

it('aceeași persoană poate ține AMBELE domenii — sunt desemnări independente', function () {
    $pagina = Livewire::test(AudienceDomainOwners::class);

    $pagina->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Instruire->value]);
    $pagina->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Educatie->value]);

    $om = $this->vicedirector->fresh();

    expect($om->handlesAudienceDomain(AudienceDomain::Instruire))->toBeTrue()
        ->and($om->handlesAudienceDomain(AudienceDomain::Educatie))->toBeTrue();
});

it('retragerea lasă domeniul descoperit, fără să atingă celălalt domeniu', function () {
    $pagina = Livewire::test(AudienceDomainOwners::class);

    $pagina->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Instruire->value]);
    $pagina->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Educatie->value]);
    $pagina->callAction('clear', arguments: ['domain' => AudienceDomain::Instruire->value]);

    $om = $this->vicedirector->fresh();

    expect($om->handlesAudienceDomain(AudienceDomain::Instruire))->toBeFalse()
        ->and($om->handlesAudienceDomain(AudienceDomain::Educatie))->toBeTrue()
        ->and(domainState(AudienceDomain::Instruire->value)['state'])->toBe('uncovered');
});

it('un cont fără rol de conducere NU poate primi un domeniu, nici cu apel forțat', function () {
    Livewire::test(AudienceDomainOwners::class)
        ->callAction('assign', ['user_id' => $this->profesor->id], arguments: ['domain' => AudienceDomain::Educatie->value]);

    expect($this->profesor->fresh()->audience_domains)->toBeNull()
        ->and(domainState(AudienceDomain::Educatie->value)['state'])->toBe('uncovered');
});

it('desemnarea rămasă după retrogradare NU se afișează ca responsabil', function () {
    Livewire::test(AudienceDomainOwners::class)
        ->callAction('assign', ['user_id' => $this->vicedirector->id], arguments: ['domain' => AudienceDomain::Educatie->value]);

    // Retrogradare: atributul rămâne în coloană, dar rolul nu-l mai poate exercita.
    $this->vicedirector->syncRoles([UserRole::Profesor->value]);

    expect(domainState(AudienceDomain::Educatie->value)['state'])->toBe('uncovered');
});

it('pagina e închisă cui nu administrează conturi', function () {
    actingAs($this->profesor->fresh());

    expect(AudienceDomainOwners::canAccess())->toBeFalse();

    actingAs($this->admin->fresh());

    expect(AudienceDomainOwners::canAccess())->toBeTrue();
});
