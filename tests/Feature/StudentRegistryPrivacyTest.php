<?php

/**
 * MINIMIZAREA DATELOR pentru PROFESOR (decizia beneficiarului, 01.08.2026): în zona Elevi,
 * profesorul vede STRICT situația pedagogică a elevilor cu care are tangență prin discipline —
 * identitate, clasă, note/absențe (deja scoped pe disciplinele lui). Detaliile de REGISTRU
 * (sex, nr. matricol, limba a 2-a, grupa, contul, istoric înmatriculări, foaia matricolă
 * completă) rămân ale administrației + DIRIGINTELUI (pe contextul activ, F3).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Students\RelationManagers\AcademicRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\ActiveRole;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => now()->subMonth()->toDateString(),
        'ends_on' => now()->addMonths(4)->toDateString(), 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);
    $this->subject = Subject::factory()->create(['name' => 'Matematică']);

    // Elev cu detalii de registru DISTINCTE (aserțiunile caută exact aceste valori).
    $this->student = Student::factory()->create([
        'last_name' => 'Priveghea', 'first_name' => 'Elev',
        'register_number' => '998877', 'english_group' => 2,
    ]);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();
});

/** Profesor cu alocare pe clasa elevului — are „tangență", dar nu e dirigintele ei. */
function registryProfesor(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

/** Dirigintele clasei (mono-rol). */
function registryDiriginte(SchoolClass $class): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class->update(['homeroom_teacher_id' => $teacher->id]);

    return $user;
}

// ─── Capabilitatea ──────────────────────────────────────────────────────────────────────

it('detaliile de registru: administrația + dirigintele DA; profesorul NU', function () {
    actingAs(registryProfesor($this->class, $this->subject));
    expect(auth()->user()->canSeeStudentRegistryDetails())->toBeFalse();

    actingAs(registryDiriginte($this->class));
    expect(auth()->user()->canSeeStudentRegistryDetails())->toBeTrue();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
    expect(auth()->user()->canSeeStudentRegistryDetails())->toBeTrue();
});

it('sub multi-rol, capabilitatea urmează CONTEXTUL: Diriginte da, Profesor nu', function () {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Diriginte->value, UserRole::Profesor->value]);
    Teacher::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    session()->put(ActiveRole::SESSION_KEY, UserRole::Diriginte->value);
    expect($user->canSeeStudentRegistryDetails())->toBeTrue();

    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);
    expect($user->canSeeStudentRegistryDetails())->toBeFalse();
});

// ─── Lista de elevi ─────────────────────────────────────────────────────────────────────

it('lista: profesorul vede identitatea, dar NU matricolul/sexul/L2/grupa/contul', function () {
    actingAs(registryProfesor($this->class, $this->subject));

    Livewire::test(ListStudents::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$this->student])
        ->assertSee('Priveghea')
        ->assertDontSee('998877')
        ->assertTableColumnHidden('sex')
        ->assertTableColumnHidden('register_number')
        ->assertTableColumnHidden('second_language')
        ->assertTableColumnHidden('english_group');
});

it('lista: dirigintele își păstrează registrul complet al clasei', function () {
    actingAs(registryDiriginte($this->class));

    Livewire::test(ListStudents::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$this->student])
        ->assertSee('998877')
        ->assertTableColumnVisible('register_number');
});

// ─── Fișa elevului (View) ───────────────────────────────────────────────────────────────

it('fișa: profesorul vede numele și clasa, dar NU chip-ul de matricol și nici grila de registru', function () {
    actingAs(registryProfesor($this->class, $this->subject));

    Livewire::test(ViewStudent::class, ['record' => $this->student->getRouteKey()])
        ->assertSee('Priveghea')
        ->assertSee(trim($this->class->name.' '.$this->class->section))
        ->assertDontSee('998877');
});

it('fișa: dirigintele vede matricolul', function () {
    actingAs(registryDiriginte($this->class));

    Livewire::test(ViewStudent::class, ['record' => $this->student->getRouteKey()])
        ->assertSee('998877');
});

// ─── Taburile fișei (relation managers) ─────────────────────────────────────────────────

it('profesorul păstrează Note/Absențe (situația pedagogică), dar pierde Înmatriculările și Foaia matricolă', function () {
    actingAs(registryProfesor($this->class, $this->subject));

    expect(GradesRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue()
        ->and(AbsencesRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue()
        ->and(EnrollmentsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeFalse()
        ->and(AcademicRecordsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeFalse();
});

it('dirigintele și administrația păstrează toate taburile de registru', function () {
    actingAs(registryDiriginte($this->class));

    expect(EnrollmentsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue()
        ->and(AcademicRecordsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue();

    $operational = User::factory()->create();
    $operational->assignRole(UserRole::AdministratorOperational->value);
    actingAs($operational);

    expect(EnrollmentsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue()
        ->and(AcademicRecordsRelationManager::canViewForRecord($this->student, ViewStudent::class))->toBeTrue();
});
