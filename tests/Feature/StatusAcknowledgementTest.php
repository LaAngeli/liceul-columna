<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StatusAcknowledgement;
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

it('familia confirmă luarea la cunoștință a statutului corigent (urmă în BD); un străin nu poate', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $term = Term::factory()->for($year)->create(['is_current' => true]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);

    // Medie sub 5 → corigent (observer-ul calculează term_average la creare).
    Grade::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $math->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
        'value' => 3,
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Confirmare necesară, încă neconfirmată; familia o poate face.
    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusAck.needed', true)
            ->where('statusAck.acknowledged', false)
            ->where('statusAck.canAcknowledge', true));

    // Un profesor străin NU poate confirma.
    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Profesor->value);
    $this->actingAs($stranger)->post("/cabinet/elev/{$student->id}/confirm-statut")->assertForbidden();

    // Familia confirmă → se înregistrează.
    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/confirm-statut")->assertRedirect();

    expect(StatusAcknowledgement::query()
        ->where('student_id', $student->id)
        ->where('acknowledged_by_user_id', $parent->id)
        ->where('status', 'corigent')
        ->exists())->toBeTrue();

    // Acum apare ca deja confirmat.
    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusAck.acknowledged', true)
            ->where('statusAck.canAcknowledge', false));
});
