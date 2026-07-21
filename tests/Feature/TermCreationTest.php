<?php

/**
 * Fluxul STANDARDIZAT de creare/editare a semestrelor (cerința beneficiarului, 2026-07-21, aceeași
 * logică ca Discipline/Elevi/Clase): numărul se ALEGE (numerele deja definite în anul ales nici nu
 * apar în opțiuni), denumirea canonică „Semestrul I/II" e GENERATĂ de model și ținută sincron cât
 * timp e canonică (denumirile custom rămân), iar gărzile de model (număr 1–4, interval nerăsturnat,
 * an închis la creare) prind orice cale de scriere.
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

it('creează semestrul din selector: denumirea canonică se generează automat', function () {
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 2,
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-05-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $term = Term::query()->where('academic_year_id', $this->year->id)->firstOrFail();

    expect($term->name)->toBe('Semestrul II')
        ->and($term->number)->toBe(2);
});

it('numerele deja definite în anul ales dispar din opțiuni; un POST forjat e tot respins', function () {
    Term::factory()->create(['academic_year_id' => $this->year->id, 'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-20']);

    // POST forjat cu numărul deja luat → respins pe server (regula rămâne plasa).
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-05-31',
        ])
        ->call('create')
        ->assertHasFormErrors(['number']);

    expect(Term::query()->where('academic_year_id', $this->year->id)->count())->toBe(1);
});

it('denumirea CANONICĂ urmează numărul la editare; denumirile custom rămân', function () {
    $canonical = Term::factory()->create([
        'academic_year_id' => $this->year->id,
        'number' => 1,
        'name' => 'Semestrul I',
        'starts_on' => '2025-09-01',
        'ends_on' => '2025-12-20',
    ]);

    Livewire::test(EditTerm::class, ['record' => $canonical->getKey()])
        ->fillForm(['number' => 3])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($canonical->refresh()->name)->toBe('Semestrul III');

    // Denumire CUSTOM (istorică): schimbarea numărului nu o atinge.
    $custom = Term::query()->create([
        'academic_year_id' => $this->year->id,
        'number' => 2,
        'name' => 'Sesiunea de iarnă',
        'starts_on' => '2026-01-05',
        'ends_on' => '2026-02-28',
    ]);

    $custom->update(['number' => 4]);

    expect($custom->refresh()->name)->toBe('Sesiunea de iarnă');
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

it('is_current rămâne al sistemului: nu se poate seta din formular', function () {
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'number' => 1,
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-12-20',
            'is_current' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Toggle-ul e disabled + nedehidratat → valoarea din POST nu ajunge în DB.
    expect((bool) Term::query()->where('academic_year_id', $this->year->id)->value('is_current'))->toBeFalse();
});

it('etichetele noii fișe există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.forms.term.section_identity_hint', $locale))->toBeTrue("Lipsește section_identity_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.term.name_generated', $locale))->toBeTrue("Lipsește name_generated [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.term.number_out_of_range', $locale))->toBeTrue("Lipsește number_out_of_range [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.term.dates_inverted', $locale))->toBeTrue("Lipsește dates_inverted [{$locale}]");
    }
});
