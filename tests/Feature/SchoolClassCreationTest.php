<?php

/**
 * Fluxul STANDARDIZAT de creare/editare a claselor (cerința beneficiarului, 2026-07-21): clasa
 * este (an, treaptă, secție) + diriginte — numele NU se tastează, e generat canonic din treaptă
 * (cifra romană) și ținut sincron cât timp e canonic; numele istorice custom rămân. Anul implicit
 * e cel curent, anii ÎNCHIȘI nu primesc clase noi (formular + model), secția se normalizează
 * (majuscule), unicitatea (an, treaptă, secție) îndrumă spre restaurare la duplicat arhivat, iar
 * dirigenția DUBLĂ rămâne permisă (realitate validată a școlii) — doar semnalată în opțiuni.
 */

use App\Enums\UserRole;
use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
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

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
});

it('creează clasa din selectoare: numele se generează canonic din treaptă, secția se salvează cu majusculă', function () {
    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 10,
            'section' => ' r ',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $class = SchoolClass::query()->where('academic_year_id', $this->year->id)->firstOrFail();

    expect($class->name)->toBe('X')
        ->and($class->section)->toBe('R')
        ->and($class->grade_level)->toBe(10);
});

it('anii ÎNCHIȘI nu primesc clase noi — respins în formular și la nivel de model', function () {
    $closed = AcademicYear::factory()->create(['closed_at' => now()]);

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $closed->id,
            'grade_level' => 5,
            'section' => '1',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_year_id']);

    // Orice cale de model e păzită la fel.
    expect(fn () => SchoolClass::factory()->create(['academic_year_id' => $closed->id]))
        ->toThrow(ValidationException::class);

    expect(SchoolClass::query()->where('academic_year_id', $closed->id)->exists())->toBeFalse();
});

it('duplicatul (an, treaptă, secție) e respins cu îndrumare spre restaurare când perechea există arhivată', function () {
    SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'grade_level' => 7, 'section' => '2']);

    // Duplicat ACTIV → respins.
    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 7,
            'section' => '2',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['section']);

    // Normalizarea prinde și duplicatul scris cu minusculă/spații.
    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 7,
            'section' => ' 2 ',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['section']);
});

it('numele CANONIC urmează treapta la editare; numele istorice custom rămân', function () {
    // Clasă canonică: treapta 5 → „V"; schimbarea treptei la 6 → numele devine „VI".
    $canonical = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'grade_level' => 5, 'name' => 'V', 'section' => 'A1']);

    Livewire::test(EditSchoolClass::class, ['record' => $canonical->getKey()])
        ->fillForm(['grade_level' => 6])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($canonical->refresh()->name)->toBe('VI');

    // Clasă cu nume CUSTOM (istoric/demo): numele nu se atinge la schimbarea treptei.
    $custom = SchoolClass::query()->create([
        'academic_year_id' => $this->year->id,
        'grade_level' => 9,
        'name' => '[DEMO] 1A',
        'section' => 'Z9',
    ]);

    $custom->update(['grade_level' => 10]);

    expect($custom->refresh()->name)->toBe('[DEMO] 1A');
});

it('garda de model respinge treapta din afara structurii, sub orice cale de scriere', function () {
    expect(fn () => SchoolClass::factory()->create(['grade_level' => 13]))
        ->toThrow(ValidationException::class);

    expect(fn () => SchoolClass::factory()->create(['grade_level' => 0]))
        ->toThrow(ValidationException::class);
});

it('dirigenția dublă rămâne PERMISĂ (realitatea validată a școlii), doar semnalată', function () {
    $teacher = Teacher::factory()->create();
    SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'grade_level' => 10, 'section' => 'R', 'homeroom_teacher_id' => $teacher->id]);

    // Al doilea mandat de diriginte în același an → acceptat fără erori.
    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 10,
            'section' => 'U',
            'homeroom_teacher_id' => $teacher->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SchoolClass::query()->where('homeroom_teacher_id', $teacher->id)->count())->toBe(2);
});

it('numele generat nu se dublează pe clasele existente: salvarea fără schimbări nu modifică nimic', function () {
    $class = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'grade_level' => 3, 'name' => 'III', 'section' => 'L']);

    Livewire::test(EditSchoolClass::class, ['record' => $class->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($class->refresh()->name)->toBe('III')
        ->and($class->section)->toBe('L');
});

it('etichetele noii fișe există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.forms.school_class.section_identity_hint', $locale))->toBeTrue("Lipsește section_identity_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.school_class.name_generated', $locale))->toBeTrue("Lipsește name_generated [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.school_class.homeroom_already', $locale))->toBeTrue("Lipsește homeroom_already [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.school_class.year_closed', $locale))->toBeTrue("Lipsește year_closed [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.school_class.grade_out_of_structure', $locale))->toBeTrue("Lipsește grade_out_of_structure [{$locale}]");
    }
});
