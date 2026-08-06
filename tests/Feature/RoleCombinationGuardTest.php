<?php

/**
 * COMBINAȚIILE DE ROLURI: interfața OPREȘTE selecțiile invalide, nu le corectează după.
 *
 * Cerința beneficiarului (06.08.2026): „Elev" se putea bifa peste „Director", iar formularul îl
 * debifa singur imediat după. Un câmp care se răzgândește singur pare defect și nu spune DE CE.
 * Acum opțiunea incompatibilă e inertă și vizibil indisponibilă ÎNAINTE de click.
 *
 * Regula e una singură — exclusivitatea familiei — și trăiește într-un singur loc
 * ({@see UserRole::isAllowedCombination}), folosit deopotrivă de garda de server și de
 * dezactivarea opțiunilor. Testele de aici apără atât predicatul, cât și faptul că cele două
 * rămân aceeași regulă.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    actingAs($this->admin->fresh());
});

// ─── Predicatul ─────────────────────────────────────────────────────────────────────────

it('funcțiile de personal se cumulă între ele, familia niciodată', function () {
    expect(UserRole::isAllowedCombination([UserRole::Director->value, UserRole::Profesor->value, UserRole::Diriginte->value]))->toBeTrue()
        ->and(UserRole::isAllowedCombination([UserRole::Elev->value]))->toBeTrue()
        ->and(UserRole::isAllowedCombination([UserRole::Parinte->value]))->toBeTrue()
        ->and(UserRole::isAllowedCombination([]))->toBeTrue()
        // Familia nu se cumulează nici cu personalul…
        ->and(UserRole::isAllowedCombination([UserRole::Director->value, UserRole::Elev->value]))->toBeFalse()
        // …nici cu cealaltă jumătate a ei: elevul și părintele sunt persoane diferite.
        ->and(UserRole::isAllowedCombination([UserRole::Elev->value, UserRole::Parinte->value]))->toBeFalse();
});

it('un rol DEJA bifat nu se dezactivează niciodată — altfel n-ai cum îndrepta date vechi', function () {
    // Combinație invalidă venită din date vechi: ambele rămân debifabile.
    expect(UserRole::blocksCombination(UserRole::Elev->value, [UserRole::Elev->value, UserRole::Director->value]))->toBeFalse()
        ->and(UserRole::blocksCombination(UserRole::Director->value, [UserRole::Elev->value, UserRole::Director->value]))->toBeFalse();
});

it('cu nimic bifat, orice rol e disponibil', function () {
    foreach (UserRole::values() as $value) {
        expect(UserRole::blocksCombination($value, []))->toBeFalse();
    }
});

// ─── Formularul ─────────────────────────────────────────────────────────────────────────

it('cu un rol de PERSONAL bifat, familia devine indisponibilă', function () {
    $dezactivate = disabledRoleOptions([UserRole::Director->value]);

    expect($dezactivate)->toEqualCanonicalizing(UserRole::familyValues());
});

it('cu ELEV bifat, TOT restul devine indisponibil — inclusiv Părinte', function () {
    $dezactivate = disabledRoleOptions([UserRole::Elev->value]);
    $asteptate = array_values(array_diff(UserRole::values(), [UserRole::Elev->value]));

    expect($dezactivate)->toEqualCanonicalizing($asteptate);
});

it('cu nimic bifat, formularul nu dezactivează nimic', function () {
    expect(disabledRoleOptions([]))->toBe([]);
});

it('serverul refuză combinația chiar dacă interfața e ocolită', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Combinatie', 'first_name' => 'Invalida',
            'username' => 'combinatie.invalida',
            'roles' => [UserRole::Director->value, UserRole::Elev->value],
        ])
        ->call('create')
        ->assertHasErrors();

    expect(User::query()->where('username', 'combinatie.invalida')->exists())->toBeFalse();
});

/**
 * Rolurile pe care formularul le marchează indisponibile pentru o selecție dată — citite din
 * componenta reală, nu re-derivate din predicat (altfel testul n-ar dovedi că sunt legate).
 *
 * @param  list<string>  $selectate
 * @return list<string>
 */
function disabledRoleOptions(array $selectate): array
{
    $form = Livewire::test(CreateUser::class)
        ->fillForm(['roles' => $selectate])
        ->instance()
        ->form;

    $component = collect($form->getFlatComponents())
        ->first(fn ($c): bool => $c instanceof CheckboxList && $c->getName() === 'roles');

    expect($component)->not->toBeNull();

    $dezactivate = [];

    foreach (array_keys($component->getOptions()) as $value) {
        if ($component->isOptionDisabled($value, (string) $value)) {
            $dezactivate[] = (string) $value;
        }
    }

    return $dezactivate;
}
