<?php

/**
 * L133/PII în profilul de cabinet (#37): motivele motivărilor (potențial PII medical de minor) +
 * lichidarea corigenței merg DOAR la familie / administrație / dirigintele elevului — nu la orice
 * profesor de disciplină care deschide profilul. Plus: jurnalul de acces nu se dublează pe reload-ul
 * parțial deferat.
 */

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Audit;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    // O motivare cu „motiv medical" (PII).
    AbsenceMotivation::factory()->create([
        'student_id' => $this->student->id,
        'reason' => 'Internare — diagnostic confidențial.',
        'status' => RequestStatus::Pending,
    ]);
});

function subjectTeacherOf(mixed $ctx): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    return $user;
}

function homeroomTeacherOf(mixed $ctx): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $ctx->class->update(['homeroom_teacher_id' => $teacher->id]);

    return $user;
}

it('profesorul de disciplină NU primește motivele motivărilor (PII medical) în profil', function () {
    $teacher = subjectTeacherOf($this);

    actingAs($teacher)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$this->student->id}", inertiaPartialHeaders('cabinet/student-profile', 'motivations'))
        ->assertOk()
        ->assertJsonPath('props.motivations', []);
});

it('dirigintele elevului primește motivările (le validează, are nevoie de context)', function () {
    $homeroom = homeroomTeacherOf($this);

    actingAs($homeroom)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$this->student->id}", inertiaPartialHeaders('cabinet/student-profile', 'motivations'))
        ->assertOk()
        ->assertJsonCount(1, 'props.motivations');
});

it('familia primește motivările propriului copil', function () {
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($this->student->id);

    actingAs($parent)
        ->get("/cabinet/elev/{$this->student->id}", inertiaPartialHeaders('cabinet/student-profile', 'motivations'))
        ->assertOk()
        ->assertJsonCount(1, 'props.motivations');
});

it('jurnalul L133 NU se dublează pe reload-ul parțial deferat (o singură intrare per deschidere)', function () {
    $director = User::factory()->create(['email_verified_at' => now()]);
    $director->assignRole(UserRole::Director->value);

    // Încărcarea inițială (fără header de partial) → o intrare „viewed".
    actingAs($director)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$this->student->id}")
        ->assertOk();

    // Reload-ul parțial deferat (props subjects/motivations) → NU mai loghează.
    actingAs($director)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$this->student->id}", inertiaPartialHeaders('cabinet/student-profile', 'motivations'))
        ->assertOk();

    $viewedCount = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $this->student->id)
        ->where('event', 'viewed')
        ->count();

    expect($viewedCount)->toBe(1);
});
