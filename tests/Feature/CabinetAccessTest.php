<?php

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('un părinte vede profilul copilului său, dar nu al altora', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $child = Student::factory()->create();
    $parent->students()->attach($child->id);
    $other = Student::factory()->create();

    $this->actingAs($parent)->get("/cabinet/elev/{$child->id}")->assertOk();
    $this->actingAs($parent)->get("/cabinet/elev/{$other->id}")->assertForbidden();
});

it('un elev își vede doar propriul profil', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $own = Student::factory()->create(['user_id' => $user->id]);
    $other = Student::factory()->create();

    $this->actingAs($user)->get("/cabinet/elev/{$own->id}")->assertOk();
    $this->actingAs($user)->get("/cabinet/elev/{$other->id}")->assertForbidden();
});

it('personalul poate vedea orice elev', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $student = Student::factory()->create();

    $this->actingAs($staff)->get("/cabinet/elev/{$student->id}")->assertOk();
});

it('redirecționează personalul de la cabinet (/dashboard) către panou (/admin)', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/admin');
})->with([
    UserRole::Admin,
    UserRole::Director,
    UserRole::PrimVicedirector,
    UserRole::AdministratorOperational,
    UserRole::AdministratorTehnic,
    UserRole::Diriginte,
    UserRole::Profesor,
]);

it('permite elevilor și părinților accesul la cabinet (/dashboard)', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/dashboard')->assertOk();
})->with([UserRole::Elev, UserRole::Parinte]);

it('homePath duce personalul la panou și restul la cabinet', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    expect($admin->homePath())->toBe('/admin');

    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);
    expect($elev->homePath())->toBe(route('dashboard'));
});

it('profilul elevului include foaie matricolă, teme și absențe defalcate', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8, 'section' => '2']);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $subject = Subject::factory()->create();
    Grade::factory()->count(3)->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
    ]);

    // 2 absențe motivate + 1 nemotivată
    Absence::factory()->count(2)->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'is_motivated' => true,
    ]);
    Absence::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'is_motivated' => false,
    ]);

    // Foaie matricolă: o treaptă anterioară
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'grade_level' => 7,
    ]);

    // Teme: una pentru clasa lui (8-2), una pentru altă literă (nu trebuie să apară)
    HomeworkAssignment::factory()->create(['grade_level' => 8, 'section' => '2']);
    HomeworkAssignment::factory()->create(['grade_level' => 8, 'section' => '9']);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/student-profile')
            ->where('absencesTotal', 3)
            ->where('absencesMotivated', 2)
            ->where('absencesUnmotivated', 1)
            ->has('transcript', 1)
            ->has('homework', 1)
        );
});

it('cabinetul arată statutul corigent cu disciplinele restante', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $term = Term::factory()->for($year)->create(['is_current' => true]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);

    // O medie sub 5 → corigent (observer-ul calculează term_average la creare).
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

    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.status', 'corigent')
            ->where('status.failingSubjects', ['Matematică']));
});

it('cabinetul traduce numele disciplinelor după limba familiei', function () {
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
        'value' => 3,
    ]);

    $parent = User::factory()->create(['locale' => 'ru']);
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.failingSubjects', ['Математика']));
});

it('cabinetul oferă formularul de motivare familiei, dar nu personalului', function () {
    $student = Student::factory()->create();

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Familia poate depune cereri de motivare.
    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('canRequestMotivation', true)
            ->has('motivations'));

    // Personalul vede pagina, dar NU formularul (ar primi 403 la trimitere).
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $this->actingAs($staff)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page->where('canRequestMotivation', false));
});

it('o cerere depusă apare în lista de motivări din cabinet', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/motivare", [
        'reason' => 'Consultație medicală',
        'period_start' => '2026-03-02',
        'period_end' => '2026-03-04',
    ])->assertRedirect();

    $this->actingAs($parent)
        ->get("/cabinet/elev/{$student->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('motivations', 1)
            ->where('motivations.0.status', 'pending')
            ->where('motivations.0.reason', 'Consultație medicală'));
});

it('răspunsul de login și cel de 2FA folosesc implementările proiectului', function () {
    expect(app(LoginResponse::class))
        ->toBeInstanceOf(App\Http\Responses\LoginResponse::class)
        ->and(app(TwoFactorLoginResponse::class))
        ->toBeInstanceOf(App\Http\Responses\TwoFactorLoginResponse::class);
});
