<?php

/**
 * Panoul „Conturi de elev incomplete" (cerința beneficiarului, 2026-08-03): fluxul de creare e
 * unificat, dar o omisiune rămânea INVIZIBILĂ. Aici se verifică exact cele două semnale care sunt
 * greșeli de operare — fără înmatriculare, fără grupă la engleză — și repararea lor pe loc.
 */

use App\Enums\UserRole;
use App\Filament\Widgets\IncompleteStudentAccounts;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
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

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create(['is_current' => true]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'B']);
});

/** Administratorul operațional — deschiderea și repararea îi aparțin. */
function incompleteWidgetOperator(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);
    actingAs($user);

    return $user;
}

/** O clasă care CHIAR se împarte pe grupe la engleză (are alocări pe grupă). */
function classWithEnglishGroups(SchoolClass $class): void
{
    $english = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'grade_levels' => range(1, 12)]);

    foreach ([1, 2] as $group) {
        TeachingAssignment::factory()->create([
            'teacher_id' => Teacher::factory()->create()->id,
            'school_class_id' => $class->id,
            'subject_id' => $english->id,
            'english_group' => $group,
        ]);
    }
}

it('se ascunde complet când nu e nimic de reparat — e o alarmă, nu un indicator', function () {
    incompleteWidgetOperator();

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($this->classA)->for($this->year)->create();

    expect(IncompleteStudentAccounts::canView())->toBeFalse();
});

it('ridică alarma pentru contul fără înmatriculare în anul curent', function () {
    incompleteWidgetOperator();

    $orfan = Student::factory()->create(['user_id' => User::factory()->create()->id]);
    $inRegula = Student::factory()->create();
    Enrollment::factory()->for($inRegula)->for($this->classA)->for($this->year)->create();

    expect(IncompleteStudentAccounts::canView())->toBeTrue();

    Livewire::test(IncompleteStudentAccounts::class)
        ->assertCanSeeTableRecords([$orfan])
        ->assertCanNotSeeTableRecords([$inRegula]);
});

it('fișa FĂRĂ cont nu e alarmă: e stare de migrare, nu greșeală de onboarding', function () {
    incompleteWidgetOperator();

    Student::factory()->create(['user_id' => null]);

    expect(IncompleteStudentAccounts::canView())->toBeFalse();
});

it('semnalează grupa lipsă doar în clasele care chiar se împart pe grupe', function () {
    incompleteWidgetOperator();

    classWithEnglishGroups($this->classA);

    $faraGrupa = Student::factory()->create(['english_group' => null]);
    Enrollment::factory()->for($faraGrupa)->for($this->classA)->for($this->year)->create();

    // Aceeași lipsă, dar într-o clasă fără grupe la engleză → nu e o problemă.
    $altaClasa = Student::factory()->create(['english_group' => null]);
    Enrollment::factory()->for($altaClasa)->for($this->classB)->for($this->year)->create();

    Livewire::test(IncompleteStudentAccounts::class)
        ->assertCanSeeTableRecords([$faraGrupa])
        ->assertCanNotSeeTableRecords([$altaClasa]);
});

it('înmatriculează pe loc, prin aceeași cale ca registrul', function () {
    incompleteWidgetOperator();

    $orfan = Student::factory()->create(['user_id' => User::factory()->create()->id]);

    Livewire::test(IncompleteStudentAccounts::class)
        ->callTableAction('enrollStudent', $orfan, ['school_class_id' => $this->classB->id]);

    $enrollment = Enrollment::query()->where('student_id', $orfan->id)->sole();

    expect($enrollment->school_class_id)->toBe($this->classB->id)
        ->and($enrollment->academic_year_id)->toBe($this->year->id)
        // Reparat → dispare din alarmă.
        ->and(IncompleteStudentAccounts::canView())->toBeFalse();
});

it('stabilește grupa la engleză pe loc', function () {
    incompleteWidgetOperator();

    classWithEnglishGroups($this->classA);

    $faraGrupa = Student::factory()->create(['english_group' => null]);
    Enrollment::factory()->for($faraGrupa)->for($this->classA)->for($this->year)->create();

    Livewire::test(IncompleteStudentAccounts::class)
        ->callTableAction('setEnglishGroup', $faraGrupa, ['english_group' => 2]);

    expect((int) $faraGrupa->fresh()->english_group)->toBe(2)
        ->and(IncompleteStudentAccounts::canView())->toBeFalse();
});

it('nu e vizibil celor fără drept de configurare', function () {
    $orfan = Student::factory()->create(['user_id' => User::factory()->create()->id]);

    foreach ([UserRole::Profesor, UserRole::Diriginte, UserRole::PrimVicedirector] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        actingAs($user);

        expect(IncompleteStudentAccounts::canView())->toBeFalse();
    }

    // Iar cine poate configura îl vede — aceeași stare a datelor.
    incompleteWidgetOperator();
    expect(IncompleteStudentAccounts::canView())->toBeTrue()
        ->and($orfan->fresh())->not->toBeNull();
});
