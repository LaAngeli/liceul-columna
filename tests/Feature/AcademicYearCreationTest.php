<?php

/**
 * Crearea GENERATĂ a anului școlar (cerința beneficiarului, 2026-07-21): denumirea nu se tastează
 * — se alege dintre candidații canonici („2026–2027", cei definiți sunt excluși → dublurile devin
 * imposibile), sistemul propune implicit primul disponibil; datele se pre-completează pe convenția
 * 01.09→30.06 cu ANUL CALENDARISTIC fixat din denumire (utilizatorul ajustează doar ziua/luna,
 * apartenența e impusă și pe server); toggle-ul „An curent" ELIMINAT; gărzile de model (format
 * canonic, interval nerăsturnat) prind orice cale de scriere.
 */

use App\Enums\UserRole;
use App\Filament\Resources\AcademicYears\Pages\CreateAcademicYear;
use App\Filament\Resources\AcademicYears\Pages\EditAcademicYear;
use App\Models\AcademicYear;
use App\Models\User;
use App\Support\SchoolCalendar;
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
});

it('propune implicit primul an disponibil și pre-completează perioada pe convenția școlii', function () {
    $currentYear = SchoolCalendar::localNow()->year;

    // Anul școlar „din urmă" și cel curent există deja → primul candidat sare peste ele.
    AcademicYear::factory()->create(['name' => AcademicYear::canonicalName($currentYear - 1)]);
    AcademicYear::factory()->create(['name' => AcademicYear::canonicalName($currentYear)]);

    $expected = AcademicYear::canonicalName($currentYear + 1);

    expect(array_key_first(AcademicYear::candidateNames()))->toBe($expected);

    // Alegerea anului RE-PROPUNE datele: 01.09.Y1 → 30.06.Y2, pe anii denumirii.
    $state = Livewire::test(CreateAcademicYear::class)
        ->fillForm(['name' => $expected])
        ->instance()->form->getRawState();

    expect($state['starts_on'])->toBe(($currentYear + 1).'-09-01')
        ->and($state['ends_on'])->toBe(($currentYear + 2).'-06-30');
});

it('creează anul complet din valorile generate, fără nicio tastare', function () {
    $first = array_key_first(AcademicYear::candidateNames());
    $startYear = AcademicYear::startYearFromName($first);

    // Formularul se deschide COMPLET propus (an + date) — crearea merge fără nicio atingere.
    Livewire::test(CreateAcademicYear::class)
        ->call('create')
        ->assertHasNoFormErrors();

    $year = AcademicYear::query()->where('name', $first)->firstOrFail();

    expect($year->starts_on?->format('Y-m-d'))->toBe($startYear.'-09-01')
        ->and($year->ends_on?->format('Y-m-d'))->toBe(($startYear + 1).'-06-30')
        ->and($year->is_current)->toBeFalse();
});

it('candidații EXCLUD anii deja definiți — inclusiv pe cei arhivați', function () {
    $currentYear = SchoolCalendar::localNow()->year;
    $taken = AcademicYear::canonicalName($currentYear);
    $archived = AcademicYear::canonicalName($currentYear + 1);

    AcademicYear::factory()->create(['name' => $taken]);
    AcademicYear::factory()->create(['name' => $archived])->delete();

    $candidates = AcademicYear::candidateNames();

    expect($candidates)->not->toHaveKey($taken)
        ->and($candidates)->not->toHaveKey($archived)
        ->and(array_key_first($candidates))->toBe(AcademicYear::canonicalName($currentYear - 1));
});

it('respinge pe SERVER un POST forjat: denumire necanonică sau an deja definit', function () {
    AcademicYear::factory()->create(['name' => '2031–2032']);

    // Format străin de convenție (ani neconsecutivi).
    Livewire::test(CreateAcademicYear::class)
        ->fillForm(['name' => '2030–2032', 'starts_on' => '2030-09-01', 'ends_on' => '2031-06-30'])
        ->call('create')
        ->assertHasFormErrors(['name']);

    // Dublură a unui an existent.
    Livewire::test(CreateAcademicYear::class)
        ->fillForm(['name' => '2031–2032', 'starts_on' => '2031-09-01', 'ends_on' => '2032-06-30'])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(AcademicYear::query()->count())->toBe(1);
});

it('respinge pe SERVER datele din afara anilor calendaristici ai denumirii', function () {
    $first = array_key_first(AcademicYear::candidateNames());
    $startYear = AcademicYear::startYearFromName($first);

    // Începutul mutat în AL DOILEA an calendaristic → respins.
    Livewire::test(CreateAcademicYear::class)
        ->fillForm([
            'name' => $first,
            'starts_on' => ($startYear + 1).'-01-15',
            'ends_on' => ($startYear + 1).'-06-30',
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_on']);

    // Sfârșitul mutat în PRIMUL an calendaristic → respins.
    Livewire::test(CreateAcademicYear::class)
        ->fillForm([
            'name' => $first,
            'starts_on' => $startYear.'-09-01',
            'ends_on' => $startYear.'-12-20',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);

    expect(AcademicYear::query()->count())->toBe(0);
});

it('respinge suprapunerea cu alt an școlar existent', function () {
    $first = array_key_first(AcademicYear::candidateNames());
    $startYear = AcademicYear::startYearFromName($first);

    // Anul precedent există și se întinde PÂNĂ PESTE 1 septembrie (31 octombrie).
    AcademicYear::factory()->create([
        'name' => AcademicYear::canonicalName($startYear - 1),
        'starts_on' => ($startYear - 1).'-09-01',
        'ends_on' => $startYear.'-10-31',
    ]);

    Livewire::test(CreateAcademicYear::class)
        ->fillForm([
            'name' => $first,
            'starts_on' => $startYear.'-09-01',
            'ends_on' => ($startYear + 1).'-06-30',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);
});

it('switch-ul „An curent" a DISPĂRUT din creare și editare', function () {
    Livewire::test(CreateAcademicYear::class)
        ->assertFormFieldDoesNotExist('is_current');

    $year = AcademicYear::factory()->create();

    Livewire::test(EditAcademicYear::class, ['record' => $year->getRouteKey()])
        ->assertFormFieldDoesNotExist('is_current');
});

it('la editare denumirea rămâne fixă, iar datele respectă tot anii denumirii', function () {
    $year = AcademicYear::factory()->create([
        'name' => '2040–2041',
        'starts_on' => '2040-09-01',
        'ends_on' => '2041-06-30',
    ]);

    // Datele se pot ajusta (ziua/luna), denumirea nu se atinge.
    Livewire::test(EditAcademicYear::class, ['record' => $year->getRouteKey()])
        ->fillForm(['starts_on' => '2040-09-02', 'ends_on' => '2041-05-31'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($year->refresh()->name)->toBe('2040–2041')
        ->and($year->starts_on?->format('Y-m-d'))->toBe('2040-09-02');

    // O dată scoasă din anul calendaristic al denumirii → respinsă și la editare.
    Livewire::test(EditAcademicYear::class, ['record' => $year->getRouteKey()])
        ->fillForm(['ends_on' => '2042-06-30'])
        ->call('save')
        ->assertHasFormErrors(['ends_on']);
});

it('gărzile de MODEL prind orice cale de scriere: format necanonic și interval răsturnat', function () {
    // Denumire în format străin (cratimă simplă, nu en-dash / ani neconsecutivi).
    expect(fn () => AcademicYear::factory()->create(['name' => '2035-2036']))
        ->toThrow(ValidationException::class);

    // Interval răsturnat, direct prin model (ocolind formularul).
    expect(fn () => AcademicYear::factory()->create([
        'name' => '2035–2036',
        'starts_on' => '2035-09-01',
        'ends_on' => '2035-08-01',
    ]))->toThrow(ValidationException::class);
});

it('mesajele și etichetele există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ([
            'panel.forms.academic_year.section_identity',
            'panel.forms.academic_year.section_period',
            'panel.forms.academic_year.convention_info',
            'panel.forms.academic_year.latest_year_info',
            'panel.validation.academic_year.name_not_canonical',
            'panel.validation.academic_year.name_taken',
            'panel.validation.academic_year.starts_outside_first_year',
            'panel.validation.academic_year.ends_outside_second_year',
            'panel.validation.academic_year.dates_inverted',
        ] as $key) {
            expect(__($key))->not->toBe($key, "Cheia {$key} lipsește pe {$locale}");
        }
    }

    app()->setLocale('ro');
});
