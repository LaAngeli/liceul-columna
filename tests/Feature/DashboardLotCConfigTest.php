<?php

use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Terms\Pages\EditTerm;
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
});

function lotCConfigurator(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

// ─── M-2: invariantul „un singur curent" ────────────────────────────────────────────────

it('setarea unui semestru curent scoate is_current de pe celelalte', function () {
    $year = AcademicYear::factory()->create();
    $first = Term::factory()->for($year)->create(['number' => 1, 'is_current' => true]);
    $second = Term::factory()->for($year)->create(['number' => 2, 'is_current' => false]);

    $second->update(['is_current' => true]);

    expect($first->fresh()->is_current)->toBeFalse()
        ->and($second->fresh()->is_current)->toBeTrue();
});

it('setarea unui an curent scoate is_current de pe ceilalți', function () {
    $first = AcademicYear::factory()->create(['is_current' => true]);
    $second = AcademicYear::factory()->create(['is_current' => false]);

    $second->update(['is_current' => true]);

    expect($first->fresh()->is_current)->toBeFalse()
        ->and($second->fresh()->is_current)->toBeTrue();
});

// ─── M-3: intervalul semestrului în an + fără suprapunere ────────────────────────────────

it('semestrul cu interval în afara anului școlar e respins', function () {
    $year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30']);
    $term = Term::factory()->for($year)->create(['starts_on' => '2025-09-01', 'ends_on' => '2025-12-31']);

    actingAs(lotCConfigurator());

    Livewire::test(EditTerm::class, ['record' => $term->id])
        ->fillForm(['ends_on' => '2026-08-15'])
        ->call('save')
        ->assertHasFormErrors(['ends_on']);
});

it('semestrul care se suprapune cu altul din același an e respins', function () {
    $year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30']);
    Term::factory()->for($year)->create(['number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-15']);
    $second = Term::factory()->for($year)->create(['number' => 2, 'starts_on' => '2026-01-16', 'ends_on' => '2026-06-30']);

    actingAs(lotCConfigurator());

    Livewire::test(EditTerm::class, ['record' => $second->id])
        ->fillForm(['starts_on' => '2026-01-10']) // intră peste primul semestru
        ->call('save')
        ->assertHasFormErrors(['ends_on']);
});

it('semestrul valid (în an, fără suprapunere) se salvează', function () {
    $year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-06-30']);
    $term = Term::factory()->for($year)->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-01-15']);

    actingAs(lotCConfigurator());

    Livewire::test(EditTerm::class, ['record' => $term->id])
        ->fillForm(['ends_on' => '2026-01-31'])
        ->call('save')
        ->assertHasNoFormErrors();
});

// ─── M-4: înmatriculare duplicată (elev + an) ────────────────────────────────────────────

it('înmatricularea duplicată (elev + an) e prinsă ca eroare de câmp, nu 500', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();
    $studentA = Student::factory()->create();
    $studentB = Student::factory()->create();

    Enrollment::factory()->for($studentA)->for($classA)->for($year)->create();
    $enrollB = Enrollment::factory()->for($studentB)->for($classB)->for($year)->create();

    actingAs(lotCConfigurator());

    Livewire::test(EditEnrollment::class, ['record' => $enrollB->id])
        ->fillForm(['student_id' => $studentA->id]) // (studentA, year) există deja
        ->call('save')
        ->assertHasFormErrors(['academic_year_id']);

    // Nu s-a scris nimic → înmatricularea lui B rămâne pe studentB.
    expect($enrollB->fresh()->student_id)->toBe($studentB->id);
});
