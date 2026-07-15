<?php

/**
 * Nomenclatoare — organizare + integritate (2026-07-15):
 *  - Discipline: intervalul de trepte nu se poate inversa și nu se poate suprapune cu o altă
 *    disciplină ACTIVĂ cu același nume (duplicatele pe trepte diferite rămân legitime:
 *    Matematică 1–4 pe calificative / 5–12 numerică).
 *  - Clase: tab-uri pe ani școlari, cu anul CURENT implicit (arhiva legacy nu se mai amestecă).
 */

use App\Enums\UserRole;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);
    actingAs($user);
});

// ─── Discipline: integritatea intervalului de trepte ─────────────────────────────────────

it('respinge intervalul de trepte INVERSAT (max < min)', function () {
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Disciplină test',
            'grading_type' => 'n',
            'min_grade' => 9,
            'max_grade' => 5,
        ])
        ->call('create')
        ->assertHasFormErrors(['max_grade' => __('panel.validation.subject.grade_span_inverted')]);

    expect(Subject::query()->where('name', 'Disciplină test')->count())->toBe(0);
});

it('respinge SUPRAPUNEREA de trepte cu o disciplină activă cu același nume', function () {
    Subject::factory()->create(['name' => 'Matematică test', 'min_grade' => 1, 'max_grade' => 4]);

    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Matematică test',
            'grading_type' => 'n',
            'min_grade' => 4,
            'max_grade' => 12,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'max_grade' => __('panel.validation.subject.grade_span_overlap', ['min' => 1, 'max' => 4]),
        ]);

    expect(Subject::query()->where('name', 'Matematică test')->count())->toBe(1);
});

it('acceptă aceeași disciplină pe trepte DISJUNCTE (duplicatul legitim din nomenclator)', function () {
    Subject::factory()->create(['name' => 'Matematică test', 'min_grade' => 1, 'max_grade' => 4]);

    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Matematică test',
            'grading_type' => 'n',
            'min_grade' => 5,
            'max_grade' => 12,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Subject::query()->where('name', 'Matematică test')->count())->toBe(2);
});

it('la editare, propriul interval nu se auto-conflictează', function () {
    $subject = Subject::factory()->create(['name' => 'Fizică test', 'min_grade' => 6, 'max_grade' => 12]);

    Livewire::test(EditSubject::class, ['record' => $subject->id])
        ->fillForm(['max_grade' => 11])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subject->refresh()->max_grade)->toBe(11);
});

// ─── Clase: tab-urile pe ani școlari ─────────────────────────────────────────────────────

it('clasele au tab-uri pe ani, cu anul CURENT implicit, iar tab-ul filtrează corect', function () {
    $oldYear = AcademicYear::factory()->create(['name' => '2019–2020']);
    $currentYear = AcademicYear::factory()->create(['name' => '2025–2026']);
    Term::factory()->for($currentYear)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $oldClass = SchoolClass::factory()->for($oldYear)->create(['name' => 'IX', 'section' => 'V']);
    $currentClass = SchoolClass::factory()->for($currentYear)->create(['name' => 'IX', 'section' => 'N']);

    $component = Livewire::test(ListSchoolClasses::class);

    // Tab-ul implicit = anul curent; vede clasa curentă, nu arhiva.
    expect($component->instance()->getDefaultActiveTab())->toBe('an-'.$currentYear->id);

    $component
        ->assertCanSeeTableRecords([$currentClass])
        ->assertCanNotSeeTableRecords([$oldClass]);

    // Tab-ul anului vechi → doar clasa lui.
    $component->set('activeTab', 'an-'.$oldYear->id)
        ->assertCanSeeTableRecords([$oldClass])
        ->assertCanNotSeeTableRecords([$currentClass]);

    // „Toate" → ambele.
    $component->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$oldClass, $currentClass]);
});
