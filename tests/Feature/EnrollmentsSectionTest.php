<?php

/**
 * Înmatriculări — registrul claselor (2026-07-16): navigator ani → carduri de clase (activi/
 * plecați) → registrul clasei (tabel scoped), adăugare PRE-COMPLETATĂ din context, elevii deja
 * înmatriculați în anul ales excluși din selecție (stratul 1; regula de pe an rămâne stratul 2 —
 * NomenclatureValidationGuardsTest), plecarea marcată direct din rând, anul stocat derivat din
 * clasă pe server.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->oldYear = AcademicYear::factory()->create(['name' => '2019–2020']);
    $this->year = AcademicYear::factory()->create(['name' => '2025–2026']);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->oldClass = SchoolClass::factory()->for($this->oldYear)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'V']);

    $this->ana = Student::factory()->create(['last_name' => 'EN-Anghel', 'first_name' => 'Ana']);
    $this->activeEnrollment = Enrollment::factory()->for($this->ana)->for($this->classA)->for($this->year)
        ->create(['enrolled_on' => '2025-09-01', 'left_on' => null]);

    $this->ion = Student::factory()->create(['last_name' => 'EN-Bostan', 'first_name' => 'Ion']);
    $this->departedEnrollment = Enrollment::factory()->for($this->ion)->for($this->classA)->for($this->year)
        ->create(['enrolled_on' => '2025-09-01', 'left_on' => '2025-12-01']);

    $this->oldEnrollment = Enrollment::factory()->for(Student::factory()->create())->for($this->oldClass)->for($this->oldYear)->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

it('registrul se navighează: ani → carduri de clase (activi/plecați) → înmatriculările clasei', function () {
    $component = Livewire::test(ListEnrollments::class);
    $page = $component->instance();

    // Pastilele anilor (cei mai noi întâi), badge = înmatriculările din registrul anului.
    expect(collect($page->yearPills())->pluck('id')->all())->toBe([$this->year->id, $this->oldYear->id])
        ->and(collect($page->yearPills())->pluck('count')->all())->toBe([2, 1])
        ->and($page->activeYearId())->toBe($this->year->id);

    // Cardul clasei: registrul pe scurt — activi ȘI plecați.
    $cards = $page->classCards();
    expect(collect($cards)->pluck('id')->all())->toBe([$this->classA->id])
        ->and($cards[0]['stats'][0])->toContain('1')
        ->and($cards[0]['stats'])->toHaveCount(2);

    // Registrul clasei = doar înmatriculările ei.
    $component->call('openClass', $this->classA->id)
        ->assertCanSeeTableRecords([$this->activeEnrollment, $this->departedEnrollment])
        ->assertCanNotSeeTableRecords([$this->oldEnrollment]);
});

it('o clasă inexistentă venită prin URL nu deschide registrul', function () {
    $component = Livewire::withQueryParams(['clasa' => '999999'])->test(ListEnrollments::class);

    expect($component->instance()->activeClass())->toBeNull();
});

it('adăugarea din registrul clasei vine pre-completată (an + clasă), iar contextul incoerent se ignoră', function () {
    Livewire::withQueryParams(['an' => (string) $this->year->id, 'clasa' => (string) $this->classA->id])
        ->test(CreateEnrollment::class)
        ->assertFormSet([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->classA->id,
        ]);

    // Doar clasa în query → anul se derivă din ea (context minim, coerent).
    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(CreateEnrollment::class)
        ->assertFormSet([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->classA->id,
        ]);

    // An explicit + clasă a ALTUI an → clasa nu se pre-completează (anul rămâne cel cerut).
    Livewire::withQueryParams(['an' => (string) $this->oldYear->id, 'clasa' => (string) $this->classA->id])
        ->test(CreateEnrollment::class)
        ->assertFormSet([
            'academic_year_id' => $this->oldYear->id,
            'school_class_id' => null,
        ]);
});

it('elevul deja înmatriculat în anul ales nu mai e oferit la selecție', function () {
    // Ana are deja înmatriculare în anul curent → nu e printre opțiuni → bariera `in` a Select-ului.
    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->classA->id,
            'student_id' => $this->ana->id,
            'enrolled_on' => '2025-09-15',
        ])
        ->call('create')
        ->assertHasFormErrors(['student_id']);

    expect(Enrollment::query()->count())->toBe(3);
});

it('înmatricularea nouă cere data și stochează anul CLASEI (derivat pe server)', function () {
    $nou = Student::factory()->create(['last_name' => 'EN-Nou', 'first_name' => 'Radu']);

    // Fără dată → obligatorie la creare.
    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->classA->id,
            'student_id' => $nou->id,
            'enrolled_on' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['enrolled_on']);

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->classA->id,
            'student_id' => $nou->id,
            'enrolled_on' => '2025-09-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $enrollment = Enrollment::query()->where('student_id', $nou->id)->sole();
    expect($enrollment->academic_year_id)->toBe($this->classA->academic_year_id);
});

it('plecarea se marchează direct din registru, doar pe rândurile active', function () {
    $component = Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class);

    // Pe rândul deja plecat, acțiunea nu există.
    $component->assertTableActionHidden('departure', $this->departedEnrollment);

    $component->callTableAction('departure', $this->activeEnrollment, ['left_on' => '2026-01-15'])
        ->assertHasNoTableActionErrors();

    expect($this->activeEnrollment->fresh()->left_on?->toDateString())->toBe('2026-01-15');
});

it('plecarea nu poate precede înmatricularea', function () {
    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->callTableAction('departure', $this->activeEnrollment, ['left_on' => '2025-08-01'])
        ->assertHasTableActionErrors(['left_on']);

    expect($this->activeEnrollment->fresh()->left_on)->toBeNull();
});

it('prim-vicedirectorul consultă registrul, dar nu operează în el', function () {
    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->assertCanSeeTableRecords([$this->activeEnrollment])
        ->assertActionHidden('create')
        ->assertTableActionHidden('departure', $this->activeEnrollment)
        ->assertTableActionHidden('edit', $this->activeEnrollment);
});
