<?php

/**
 * Fluxul GHIDAT de creare a semestrului (cerința beneficiarului, 2026-07-21): wizard în trei pași
 * (an → identificare → perioadă) cu validare per pas; numărul din listă controlată (cele definite
 * dispar), denumirea completată automat dar EDITABILĂ justificat; începutul propus pe prima zi
 * liberă; switch-ul „Semestru curent" ELIMINAT din formular (statutul e al sistemului); gărzile
 * de model (număr 1–4, interval nerăsturnat, an închis) prind orice cale de scriere.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Terms\Pages\CreateTerm;
use App\Filament\Resources\Terms\Pages\EditTerm;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);

    $this->year = AcademicYear::factory()->create([
        'is_current' => true,
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
});

it('wizard-ul creează semestrul în trei pași, cu denumirea completată automat', function () {
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 2,
            'name' => 'Semestrul II',
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-05-31',
        ])
        ->goToWizardStep(3)
        ->call('create')
        ->assertHasNoFormErrors();

    $term = Term::query()->where('academic_year_id', $this->year->id)->firstOrFail();

    expect($term->number)->toBe(2)
        ->and($term->name)->toBe('Semestrul II');
});

it('alegerea numărului completează automat denumirea și propune începutul pe prima zi liberă', function () {
    Term::factory()->create([
        'academic_year_id' => $this->year->id,
        'number' => 1,
        'name' => 'Semestrul I',
        'starts_on' => '2025-09-01',
        'ends_on' => '2025-12-20',
    ]);

    $component = Livewire::test(CreateTerm::class)
        ->fillForm(['academic_year_id' => $this->year->id])
        ->fillForm(['number' => 2]);

    $state = $component->instance()->form->getRawState();

    expect($state['name'])->toBe('Semestrul II')
        // Prima zi liberă = ziua de după sfârșitul Semestrului I.
        ->and($state['starts_on'])->toBe('2025-12-21');
});

it('denumirea rămâne EDITABILĂ: o denumire justificată nu e suprascrisă și se salvează', function () {
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'name' => 'Sesiunea de toamnă',
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-12-20',
        ])
        ->goToWizardStep(3)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Term::query()->where('academic_year_id', $this->year->id)->value('name'))
        ->toBe('Sesiunea de toamnă');
});

it('numerele deja definite dispar din listă; un POST forjat e respins pe server', function () {
    Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-20']);

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'name' => 'Semestrul I',
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-05-31',
        ])
        ->goToWizardStep(3)
        ->call('create')
        ->assertHasFormErrors(['number']);

    expect(Term::query()->where('academic_year_id', $this->year->id)->count())->toBe(1);
});

it('switch-ul „Semestru curent" a dispărut din formulare — statutul e al sistemului', function () {
    Livewire::test(CreateTerm::class)
        ->assertFormFieldDoesNotExist('is_current');

    $term = Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-20']);

    Livewire::test(EditTerm::class, ['record' => $term->getKey()])
        ->assertFormFieldDoesNotExist('is_current');
});

it('validarea pe pași oprește înaintarea fără numărul ales', function () {
    Livewire::test(CreateTerm::class)
        ->fillForm(['academic_year_id' => $this->year->id])
        ->goToWizardStep(2)
        ->assertWizardCurrentStep(2)
        ->goToNextWizardStep()
        ->assertHasFormErrors(['number']);
});

it('anul are exact DOUĂ semestre: numărul 3 e respins, iar anul plin nu mai oferă nicio opțiune', function () {
    // Structura reală a școlii: doar semestrele 1 și 2 (foaia matricolă cunoaște Sem I/II/anuala).
    expect(fn () => Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 3]))
        ->toThrow(ValidationException::class);

    // An cu ambele semestre definite → POST forjat cu orice număr e respins (1/2 luate, 3 invalid).
    Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-20']);
    Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 2, 'starts_on' => '2026-01-05', 'ends_on' => '2026-05-31']);

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 2,
            'name' => 'Semestrul II',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
        ])
        ->goToWizardStep(3)
        ->call('create')
        ->assertHasFormErrors(['number']);

    expect(Term::query()->where('academic_year_id', $this->year->id)->count())->toBe(2);
});

it('intervalul răsturnat nu poate fi nici SELECTAT, nici SALVAT', function () {
    // UI: mutarea începutului DUPĂ sfârșitul deja ales golește sfârșitul pe loc (live).
    $component = Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'ends_on' => '2026-06-01',
        ])
        ->fillForm(['starts_on' => '2026-06-08']);

    expect($component->instance()->form->getRawState()['ends_on'])->toBeNull();

    // Server: un POST forjat cu ambele date răsturnate (ocolind reactivitatea) e respins,
    // nimic nu ajunge în bază — raportat de beneficiar pe 21.07 (06.08 → 01.06).
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'name' => 'Semestrul I',
            'starts_on' => '2026-06-08',
            'ends_on' => '2026-06-01',
        ])
        ->goToWizardStep(3)
        ->call('create')
        ->assertHasFormErrors(['ends_on']);

    expect(Term::query()->where('academic_year_id', $this->year->id)->exists())->toBeFalse();
});

it('gărzile de model prind orice cale: număr în afara plajei, interval răsturnat, an închis', function () {
    expect(fn () => Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 5]))
        ->toThrow(ValidationException::class);

    expect(fn () => Term::factory()->create([
        'academic_year_id' => $this->year->id,
        'number' => 1,
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-01-01',
    ]))->toThrow(ValidationException::class);

    $closed = AcademicYear::factory()->create(['closed_at' => now()]);

    expect(fn () => Term::factory()->create(['academic_year_id' => $closed->id, 'number' => 1]))
        ->toThrow(ValidationException::class);
});

it('etichetele fluxului ghidat există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.forms.term.step_year', $locale))->toBeTrue("Lipsește step_year [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.term.step_period_hint', $locale))->toBeTrue("Lipsește step_period_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.term.name_autofill_hint', $locale))->toBeTrue("Lipsește name_autofill_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.term.year_period_info', $locale))->toBeTrue("Lipsește year_period_info [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.term.number_out_of_range', $locale))->toBeTrue("Lipsește number_out_of_range [{$locale}]");
    }
});
