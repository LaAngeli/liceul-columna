<?php

/**
 * Coerența cockpitului (#37): statutul REPETENT aprinde alerta (nu doar corigent/amânat), iar un
 * elev legat și prin user_id și prin pivotul guardian_student apare O SINGURĂ dată (fără dublă
 * numărare la „cu risc").
 */

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->withoutVite();

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['number' => 2, 'is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
});

it('StudentStatus::isAtRisk — corigent/amânat/repetent cer atenția, promovat nu', function () {
    expect(StudentStatus::Corigent->isAtRisk())->toBeTrue()
        ->and(StudentStatus::Amanat->isAtRisk())->toBeTrue()
        ->and(StudentStatus::Repetent->isAtRisk())->toBeTrue()
        ->and(StudentStatus::Promovat->isAtRisk())->toBeFalse();
});

it('copilul REPETENT aprinde alerta cockpitului (cel mai grav statut nu mai e „ignorat")', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $child = Student::factory()->create();
    Enrollment::factory()->for($child)->for($this->class)->for($this->year)->create();
    $parent->students()->attach($child->id);

    // Statut OFICIAL repetent (Consiliu + ordin).
    SemesterValidation::create([
        'student_id' => $child->id,
        'term_id' => $this->term->id,
        'validated_by_user_id' => User::factory()->create()->id,
        'status' => StudentStatus::Repetent->value,
        'order_reference' => 'Ordin 12/2026',
        'validated_at' => now(),
    ]);

    actingAs($parent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('cabinet.alerts.at_risk', 1)
            ->where('cabinet.children.0.isAtRisk', true)
            ->where('cabinet.children.0.statusValue', StudentStatus::Repetent->value)
        );
});

it('elevul legat și prin user_id și prin pivot apare O SINGURĂ dată (fără card/număr dublu)', function () {
    $account = User::factory()->create();
    $account->assignRole(UserRole::Elev->value);

    $student = Student::factory()->create(['user_id' => $account->id]);
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();
    // Aceeași fișă legată ȘI prin pivotul guardian_student.
    $account->students()->attach($student->id);

    actingAs($account)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('cabinet.self.id', $student->id)
            ->has('cabinet.children', 0)          // self exclus din copii → fără card dublu
            ->where('cabinet.alerts.at_risk', 0)  // un singur elev, promovat → numărat o dată
        );
});
