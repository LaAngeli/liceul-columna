<?php

/**
 * Rafinările fluxului „elev nou" (cerința beneficiarului, 2026-08-03, punctele 4–7):
 * numărul matricol propus PE CLASĂ, grupa la engleză cerută unde clasa se împarte pe grupe,
 * fișa fără cont de acces și garda pe semestrul curent.
 *
 * ⚠️ Numărul matricol NU e unic pe școală — e ordinea elevului ÎN CLASĂ (măsurat pe datele reale:
 * maximul 30, „1" apare în 19 clase). Toate regulile de aici lucrează pe clasă.
 */

use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassRoster;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->operator = User::factory()->create();
    $this->operator->assignRole(UserRole::Director->value);
    actingAs($this->operator);

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'B']);
});

/** Elev înmatriculat în clasă, cu numărul dat. */
function rosterStudent(SchoolClass $class, ?string $number, ?int $group = null): Student
{
    $student = Student::factory()->create(['register_number' => $number, 'english_group' => $group]);

    Enrollment::factory()->for($student)->for($class)->for($class->academicYear)->create();

    return $student;
}

/** Clasa lucrează pe grupe la engleză (are alocări pe grupă). */
function splitEnglishClass(SchoolClass $class): void
{
    $english = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'min_grade' => 1, 'max_grade' => 12]);

    foreach ([1, 2] as $group) {
        TeachingAssignment::factory()->create([
            'teacher_id' => Teacher::factory()->create()->id,
            'school_class_id' => $class->id,
            'subject_id' => $english->id,
            'english_group' => $group,
        ]);
    }
}

// ─── 4. Numărul matricol: ordinea ÎN CLASĂ ───────────────────────────────────────────────

it('propune primul număr liber din clasă, nu un id pe școală', function () {
    rosterStudent($this->classA, '1');
    rosterStudent($this->classA, '2');
    rosterStudent($this->classA, '4');

    // Aceleași numere există legitim în altă clasă — nu influențează propunerea.
    rosterStudent($this->classB, '1');
    rosterStudent($this->classB, '2');
    rosterStudent($this->classB, '3');

    expect(ClassRoster::nextRegisterNumber($this->classA->id))->toBe('3')
        ->and(ClassRoster::nextRegisterNumber($this->classB->id))->toBe('4')
        ->and(ClassRoster::usedRegisterNumbers($this->classA->id))->toBe([1, 2, 4]);
});

it('respinge un număr deja purtat în clasa aleasă, dar îl acceptă în alta', function () {
    rosterStudent($this->classA, '7');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Numar', 'first_name' => 'Ocupat',
            'username' => 'numar.ocupat',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $this->classA->id,
            'student_fiche_register_number' => '7',
            'password' => 'Temp-Numar-1',
        ])
        ->call('create')
        ->assertHasFormErrors(['student_fiche_register_number']);

    // Același număr, în clasa B: legitim (e ordinea în clasă, nu un identificator unic).
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Numar', 'first_name' => 'Liber',
            'username' => 'numar.liber',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Female->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $this->classB->id,
            'student_fiche_register_number' => '7',
            'password' => 'Temp-Numar-2',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Student::query()->where('first_name', 'Liber')->sole()->register_number)->toBe('7');
});

// ─── 5. Grupa la engleză ─────────────────────────────────────────────────────────────────

it('propune grupa mai puțin populată a clasei', function () {
    splitEnglishClass($this->classA);
    rosterStudent($this->classA, '1', 1);
    rosterStudent($this->classA, '2', 1);
    rosterStudent($this->classA, '3', 2);

    expect(ClassRoster::suggestEnglishGroup($this->classA->id))->toBe(2)
        // Clasa fără grupe la engleză nu propune nimic — n-are ce.
        ->and(ClassRoster::suggestEnglishGroup($this->classB->id))->toBeNull();
});

it('cere grupa doar în clasele care se împart pe grupe, și o scrie pe fișă', function () {
    splitEnglishClass($this->classA);

    // Clasa fără grupe: fluxul trece fără să ceară nimic.
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Grupe',
            'username' => 'fara.grupe',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $this->classB->id,
            'password' => 'Temp-Grupa-1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Student::query()->where('first_name', 'Grupe')->sole()->english_group)->toBeNull();

    // Clasa CU grupe: câmpul e obligatoriu…
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Cu', 'first_name' => 'Grupe',
            'username' => 'cu.grupe',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Female->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $this->classA->id,
            'student_fiche_english_group' => null,
            'password' => 'Temp-Grupa-2',
        ])
        ->call('create')
        ->assertHasFormErrors(['student_fiche_english_group']);

    // …și ajunge pe fișă.
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Cu', 'first_name' => 'Grupata',
            'username' => 'cu.grupa2',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Female->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $this->classA->id,
            'student_fiche_english_group' => 2,
            'password' => 'Temp-Grupa-3',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((int) Student::query()->where('first_name', 'Grupata')->sole()->english_group)->toBe(2);
});

// ─── 6. Fișă fără cont de acces ──────────────────────────────────────────────────────────

it('adaugă elevul fără cont: fișă + înmatriculare, nicio autentificare creată', function () {
    Livewire::test(ListStudents::class)
        ->callAction('createFicheOnly', [
            'last_name' => 'Primar', 'first_name' => 'Copil',
            'sex' => Sex::Male->value,
            'school_class_id' => $this->classA->id,
            'register_number' => '1',
            'second_language' => SecondLanguage::None->value,
        ]);

    $student = Student::query()->where('last_name', 'Primar')->sole();

    expect($student->user_id)->toBeNull()
        ->and($student->register_number)->toBe('1')
        ->and(Enrollment::query()->where('student_id', $student->id)->sole()->school_class_id)->toBe($this->classA->id)
        // Niciun cont nou în urma operațiunii (doar operatorul autentificat).
        ->and(User::query()->count())->toBe(1);
});

it('elevul fără cont apare în catalogul clasei ca oricare altul', function () {
    Livewire::test(ListStudents::class)
        ->callAction('createFicheOnly', [
            'last_name' => 'Vizibil', 'first_name' => 'InCatalog',
            'sex' => Sex::Female->value,
            'school_class_id' => $this->classA->id,
            'second_language' => SecondLanguage::None->value,
        ]);

    $student = Student::query()->where('last_name', 'Vizibil')->sole();

    Livewire::test(ListStudents::class)
        ->call('openCatalogEntity', $this->classA->id)
        ->assertCanSeeTableRecords([$student]);
});

it('acțiunea „fără cont" e închisă celor care nu configurează școala', function () {
    // Prim-vicedirectorul VEDE registrul (isAdministrator), dar nu configurează școala.
    $vicedirector = User::factory()->create();
    $vicedirector->assignRole(UserRole::PrimVicedirector->value);
    actingAs($vicedirector);

    Livewire::test(ListStudents::class)->assertActionHidden('createFicheOnly');

    // Profesorul cu fișă și clasă: la fel, doar consultă registrul.
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->classA->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);
    actingAs($profesor);

    Livewire::test(ListStudents::class)->assertActionHidden('createFicheOnly');
});

// ─── 7. Garda pe semestrul curent ────────────────────────────────────────────────────────

it('fără semestru curent nu se ghicește anul: nicio clasă de înmatriculare oferită', function () {
    $this->term->update(['is_current' => false]);

    // Există un an mai NOU (id mai mare) cu clase — vechiul fallback l-ar fi ales tăcut.
    $fantoma = AcademicYear::factory()->create(['name' => '2037–2038', 'starts_on' => '2037-09-01', 'ends_on' => '2038-06-30']);
    SchoolClass::factory()->for($fantoma)->create(['grade_level' => 9, 'name' => 'IX', 'section' => 'F']);

    expect(ClassRoster::enrollmentYearId())->toBeNull();

    // Acțiunea „fără cont" nici nu se oferă…
    Livewire::test(ListStudents::class)->assertActionHidden('createFicheOnly');

    // …iar în onboarding nicio clasă nu poate fi aleasă (deci nici cea a anului-fantomă).
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Semestru',
            'username' => 'fara.semestru',
            'roles' => [UserRole::Elev->value],
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'student_fiche_second_language' => SecondLanguage::None->value,
            'enroll_class_id' => $fantoma->schoolClasses()->first()->id,
            'password' => 'Temp-Fantoma',
        ])
        ->call('create')
        ->assertHasFormErrors(['enroll_class_id']);

    expect(User::query()->where('username', 'fara.semestru')->exists())->toBeFalse();
});
