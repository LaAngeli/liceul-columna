<?php

/**
 * „Consimțăminte" restructurat (2026-07-17): secțiunea răspunde la întrebarea de conformitate —
 * cine a confirmat versiunea CURENTĂ a notei de informare și cine NU. Carduri pe segment
 * (Elevi/Părinți) cu acoperire → context cu vederile Dovezi / De confirmat.
 */

use App\Enums\UserRole;
use App\Filament\Resources\ConsentAcknowledgments\Pages\ListConsentAcknowledgments;
use App\Models\ConsentAcknowledgment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    config(['privacy.notice_version' => '2026-06-28']);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

/** Un cont de segment (elev/părinte) cu starea de confirmare dată. */
function consentUser(UserRole $role, ?string $version, array $overrides = []): User
{
    $user = User::factory()->create([
        'privacy_acknowledged_version' => $version,
        'privacy_acknowledged_at' => $version !== null ? now() : null,
        ...$overrides,
    ]);
    $user->assignRole($role->value);

    return $user;
}

function proofFor(User $user, string $version): ConsentAcknowledgment
{
    return ConsentAcknowledgment::create([
        'user_id' => $user->id,
        'document_version' => $version,
        'acknowledged_at' => now(),
        'ip_address' => '127.0.0.1',
    ]);
}

it('cardurile arată acoperirea versiunii curente per segment, fără conturile suspendate', function () {
    $confirmed1 = consentUser(UserRole::Elev, '2026-06-28');
    $confirmed2 = consentUser(UserRole::Elev, '2026-06-28');
    consentUser(UserRole::Elev, null);                                  // niciodată
    consentUser(UserRole::Elev, '2025-01-01');                          // versiune veche
    consentUser(UserRole::Elev, null, ['suspended_at' => now()]);       // suspendat — nu intră
    $parinte = consentUser(UserRole::Parinte, '2026-06-28');

    proofFor($confirmed1, '2026-06-28');
    proofFor($confirmed2, '2026-06-28');
    proofFor($parinte, '2026-06-28');

    $cards = collect(Livewire::test(ListConsentAcknowledgments::class)->instance()->roleCards());

    expect($cards->pluck('id')->all())->toBe([UserRole::Elev->value, UserRole::Parinte->value]);

    $elevi = $cards->firstWhere('id', UserRole::Elev->value);
    expect($elevi['stats'][0])->toContain('2')
        ->and($elevi['stats'][0])->toContain('4')
        ->and($elevi['percent'])->toBe(50)
        ->and($elevi['badge'])->toContain('2');

    $parinti = $cards->firstWhere('id', UserRole::Parinte->value);
    expect($parinti['percent'])->toBe(100)
        ->and($parinti['badge'])->toBeNull();
});

it('contextul unui segment arată doar dovezile lui; un segment inventat nu deschide context', function () {
    $elev = consentUser(UserRole::Elev, '2026-06-28');
    $parinte = consentUser(UserRole::Parinte, '2026-06-28');

    $elevProof = proofFor($elev, '2026-06-28');
    $parinteProof = proofFor($parinte, '2026-06-28');

    Livewire::withQueryParams(['rol' => 'elev'])
        ->test(ListConsentAcknowledgments::class)
        ->assertCanSeeTableRecords([$elevProof])
        ->assertCanNotSeeTableRecords([$parinteProof]);

    // Personalul nu e segment vizat — „profesor" nu deschide context.
    expect(Livewire::withQueryParams(['rol' => 'profesor'])->test(ListConsentAcknowledgments::class)->instance()->activeRole())
        ->toBeNull();
});

it('vederea „De confirmat" listează conturile active fără versiunea curentă, cu starea lor', function () {
    consentUser(UserRole::Elev, '2026-06-28', ['name' => 'Confirmat Deja']);
    consentUser(UserRole::Elev, null, ['name' => 'Fara Confirmare']);
    consentUser(UserRole::Elev, '2025-01-01', ['name' => 'Versiune Veche']);
    consentUser(UserRole::Elev, null, ['name' => 'Suspendat Exclus', 'suspended_at' => now()]);

    $missing = Livewire::withQueryParams(['rol' => 'elev', 'restanta' => '1'])
        ->test(ListConsentAcknowledgments::class)
        ->instance()
        ->missingUsers();

    expect($missing['total'])->toBe(2)
        ->and(collect($missing['users'])->pluck('name')->all())->toBe(['Fara Confirmare', 'Versiune Veche'])
        ->and(collect($missing['users'])->firstWhere('name', 'Fara Confirmare')['previous'])->toBeNull()
        ->and(collect($missing['users'])->firstWhere('name', 'Versiune Veche')['previous'])->toBe('2025-01-01');
});

it('căutarea din „De confirmat" îngustează lista, dar totalul segmentului rămâne', function () {
    consentUser(UserRole::Elev, null, ['name' => 'Popescu Ana']);
    consentUser(UserRole::Elev, null, ['name' => 'Rusu Ion']);

    $page = Livewire::withQueryParams(['rol' => 'elev', 'restanta' => '1'])
        ->test(ListConsentAcknowledgments::class)
        ->set('missingSearch', 'Popescu');

    $missing = $page->instance()->missingUsers();

    expect($missing['total'])->toBe(2)
        ->and(collect($missing['users'])->pluck('name')->all())->toBe(['Popescu Ana']);
});
