<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('statutul OFICIAL validat de conducere primează asupra celui calculat, în cabinet', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $term = Term::factory()->for($year)->create(['is_current' => true]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);
    Grade::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $math->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
        'value' => 3, // medie < 5 → corigent calculat
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Fără validare: corigent (calculat automat), neoficial.
    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.status', 'corigent')
            ->where('status.official', false));

    // Conducerea validează OFICIAL ca „amânat", cu ordin.
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    SemesterValidation::create([
        'student_id' => $student->id,
        'term_id' => $term->id,
        'validated_by_user_id' => $director->id,
        'status' => 'amanat',
        'order_reference' => 'Ordin 12/2026',
        'validated_at' => now(),
    ]);

    // Acum primează statutul oficial.
    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.status', 'amanat')
            ->where('status.official', true)
            ->where('status.orderReference', 'Ordin 12/2026'));
});
