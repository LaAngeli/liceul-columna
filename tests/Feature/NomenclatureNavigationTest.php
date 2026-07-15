<?php

/**
 * Nomenclatoare — organizare + integritate (2026-07-15):
 *  - Discipline: intervalul de trepte nu se poate inversa și nu se poate suprapune cu o altă
 *    disciplină ACTIVĂ cu același nume (duplicatele pe trepte diferite rămân legitime:
 *    Matematică 1–4 pe calificative / 5–12 numerică).
 *  - Clase: navigator cu CARDURI pe ani școlari („ca la Elevi"), anul CURENT implicit, sărituri
 *    directe în catalog pe contextul clasei; „Editare" doar pentru configuratori; clasele șterse
 *    au vederea dedicată a administrației (restaurarea trăiește în Editare).
 */

use App\Enums\UserRole;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
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

// ─── Clase: navigator cu carduri pe ani școlari ──────────────────────────────────────────

it('clasele sunt CARDURI pe ani, cu anul CURENT implicit și sărituri în catalog', function () {
    $oldYear = AcademicYear::factory()->create(['name' => '2019–2020']);
    $currentYear = AcademicYear::factory()->create(['name' => '2025–2026']);
    Term::factory()->for($currentYear)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $oldClass = SchoolClass::factory()->for($oldYear)->create(['name' => 'IX', 'section' => 'V']);
    $currentClass = SchoolClass::factory()->for($currentYear)->create(['name' => 'IX', 'section' => 'N']);

    $component = Livewire::test(ListSchoolClasses::class);
    $page = $component->instance();

    // Anul implicit = cel curent; pastilele acoperă ambii ani (cei mai noi întâi), cu numărul de clase.
    expect($page->activeYearId())->toBe($currentYear->id)
        ->and(collect($page->yearPills())->pluck('id')->all())->toBe([$currentYear->id, $oldYear->id])
        ->and(collect($page->yearPills())->pluck('count')->all())->toBe([1, 1]);

    // Cardurile anului curent: doar clasa lui, cu sărituri pe contextul clasei + Editare (director).
    $cards = $page->classCards();
    expect(collect($cards)->pluck('id')->all())->toBe([$currentClass->id]);

    foreach ($cards[0]['links'] as $url) {
        expect($url)->toContain('clasa='.$currentClass->id);
    }
    expect($cards[0]['edit_url'])->not->toBeNull();

    // Anul vechi → doar clasa lui.
    $component->call('openYear', $oldYear->id);
    expect(collect($component->instance()->classCards())->pluck('id')->all())->toBe([$oldClass->id]);
});

it('un an cerut prin URL care nu există cade pe anul curent', function () {
    $currentYear = AcademicYear::factory()->create(['name' => '2025–2026']);
    Term::factory()->for($currentYear)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);
    SchoolClass::factory()->for($currentYear)->create(['name' => 'V', 'section' => 'Q']);

    $component = Livewire::withQueryParams(['an' => '999999'])->test(ListSchoolClasses::class);

    expect($component->instance()->activeYearId())->toBe($currentYear->id);
});

it('profesorul vede DOAR clasele lui drept carduri, fără Editare', function () {
    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);
    $mine = SchoolClass::factory()->for($year)->create(['name' => 'VII', 'section' => 'M']);
    $foreign = SchoolClass::factory()->for($year)->create(['name' => 'VIII', 'section' => 'F']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $mine->id, 'subject_id' => Subject::factory()->create()->id,
    ]);

    actingAs($user);

    $cards = Livewire::test(ListSchoolClasses::class)->instance()->classCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$mine->id])
        ->and($cards[0]['edit_url'])->toBeNull();

    expect(collect($cards)->pluck('id')->all())->not->toContain($foreign->id);
});

it('clasa cu elevi dar fără diriginte funcțional e semnalată pe card', function () {
    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    // Clasă ACTIVĂ (are elevi) fără diriginte → semnalată; clasă GOALĂ fără diriginte → nu.
    $active = SchoolClass::factory()->for($year)->create(['name' => 'VI', 'section' => 'S']);
    Enrollment::factory()->create(['school_class_id' => $active->id, 'academic_year_id' => $year->id]);
    $empty = SchoolClass::factory()->for($year)->create(['name' => 'VI', 'section' => 'T']);

    $cards = collect(Livewire::test(ListSchoolClasses::class)->instance()->classCards())->keyBy('id');

    expect($cards->get($active->id)['missing_homeroom'])->toBeTrue()
        ->and($cards->get($empty->id)['missing_homeroom'])->toBeFalse();
});

it('clasele șterse au vederea dedicată a administrației, cu restaurarea prin Editare', function () {
    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);
    $kept = SchoolClass::factory()->for($year)->create(['name' => 'X', 'section' => 'K']);
    $deleted = SchoolClass::factory()->for($year)->create(['name' => 'X', 'section' => 'D']);
    $deleted->delete();

    // Directorul: vederea „Șterse" arată clasa arhivată, cu drum spre Editare (restaurare).
    $component = Livewire::withQueryParams(['sterse' => '1'])->test(ListSchoolClasses::class);
    $page = $component->instance();

    expect($page->isTrashedMode())->toBeTrue();

    $trashed = collect($page->trashedCards());
    expect($trashed->pluck('id')->all())->toBe([$deleted->id])
        ->and($trashed->first()['edit_url'])->not->toBeNull();

    // Clasa ștearsă NU apare printre cardurile obișnuite.
    expect(collect($page->classCards())->pluck('id')->all())->toBe([$kept->id]);

    // Profesorul nu are vederea „Șterse" — parametrul din URL e ignorat.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect(Livewire::withQueryParams(['sterse' => '1'])->test(ListSchoolClasses::class)->instance()->isTrashedMode())
        ->toBeFalse();
});
