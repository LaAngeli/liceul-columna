<?php

/**
 * ONBOARDING UNIFICAT (cerința beneficiarului, 2026-07-16): crearea unui profesor/elev = UN
 * SINGUR FLUX care naște împreună fișa + contul + integrarea în module — alocările clasă×
 * disciplină (perimetrul catalogului), clasa de diriginție, înmatricularea elevului în anul
 * curent și legătura cu conturile de părinte. Fișele nu se mai creează separat: paginile
 * directe de creare sunt închise, iar butoanele din registre duc în fluxul de cont.
 */

use App\Enums\Sex;
use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
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

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);

    // Anul „de lucru" (semestrul curent) + două clase și două discipline pentru integrare.
    $this->year = AcademicYear::factory()->create();
    Term::factory()->create(['academic_year_id' => $this->year->id, 'is_current' => true]);
    $this->classA = SchoolClass::factory()->create(['academic_year_id' => $this->year->id]);
    $this->classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id]);
    $this->math = Subject::factory()->create(['name' => 'Matematica']);
    $this->chem = Subject::factory()->create(['name' => 'Chimia']);
});

// ─── Profesor: fișă nouă + cont + alocări într-un singur flux ────────────────────────────

it('onboarding profesor: contul, fișa și alocările se nasc împreună, gata de catalog', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Onboard', 'first_name' => 'Profesoara',
            'username' => 'onboard.prof',
            'email' => 'onboard.prof@test.columna',
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'create',
            'teacher_fiche_sex' => Sex::Female->value,
            'teacher_fiche_position' => 'Profesoară de matematică',
            'teaching_pairs' => [
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
                ['school_class_id' => $this->classB->id, 'subject_id' => $this->chem->id],
            ],
            'password' => 'Temp-Onboard-1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'onboard.prof')->sole();
    $fiche = Teacher::query()->where('user_id', $user->id)->sole();

    // Fișa preia identitatea contului (nume + e-mail) și datele proprii.
    expect($fiche->last_name)->toBe('Onboard')
        ->and($fiche->first_name)->toBe('Profesoara')
        ->and($fiche->sex)->toBe(Sex::Female)
        ->and($fiche->position)->toBe('Profesoară de matematică')
        ->and($fiche->email)->toBe('onboard.prof@test.columna');

    // Integrarea în catalog: perimetrul (alocările) e funcțional din prima zi.
    expect(TeachingAssignment::query()->where('teacher_id', $fiche->id)->count())->toBe(2)
        ->and($fiche->canGradeClassSubject($this->classA->id, $this->math->id))->toBeTrue()
        ->and($fiche->canGradeClassSubject($this->classB->id, $this->chem->id))->toBeTrue()
        ->and($fiche->canGradeClassSubject($this->classA->id, $this->chem->id))->toBeFalse();
});

it('onboarding diriginte: primește pe loc clasa de diriginție (doar clasele libere)', function () {
    // Clasa B e deja ocupată de alt diriginte → nu poate fi aleasă.
    $altDiriginte = Teacher::factory()->create();
    $this->classB->update(['homeroom_teacher_id' => $altDiriginte->id]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Onboard', 'first_name' => 'Diriginta',
            'username' => 'onboard.dir',
            'role' => UserRole::Diriginte->value,
            'teacher_fiche_mode' => 'create',
            'teacher_fiche_sex' => Sex::Female->value,
            'teaching_pairs' => [
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
            ],
            'homeroom_class_id' => $this->classB->id,
            'password' => 'Temp-Onboard-2',
        ])
        ->call('create')
        ->assertHasFormErrors(['homeroom_class_id']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Onboard', 'first_name' => 'Diriginta',
            'username' => 'onboard.dir',
            'role' => UserRole::Diriginte->value,
            'teacher_fiche_mode' => 'create',
            'teacher_fiche_sex' => Sex::Female->value,
            'teaching_pairs' => [
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
            ],
            'homeroom_class_id' => $this->classA->id,
            'password' => 'Temp-Onboard-2',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'onboard.dir')->sole();
    $fiche = Teacher::query()->where('user_id', $user->id)->sole();

    expect($this->classA->fresh()->homeroom_teacher_id)->toBe($fiche->id)
        // Diriginția + alocarea îi dau perimetrul complet (clasa mea + disciplina mea).
        ->and($this->classB->fresh()->homeroom_teacher_id)->toBe($altDiriginte->id);
});

it('onboarding profesor NOU fără alocări nu trece: perimetrul e obligatoriu', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Alocari',
            'username' => 'fara.alocari',
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'create',
            'teacher_fiche_sex' => Sex::Male->value,
            'teaching_pairs' => [],
            'password' => 'Temp-Onboard-3',
        ])
        ->call('create')
        ->assertHasFormErrors(['teaching_pairs']);

    expect(User::query()->where('username', 'fara.alocari')->exists())->toBeFalse()
        ->and(Teacher::query()->where('last_name', 'Fara')->exists())->toBeFalse();
});

it('perechile clasă×disciplină duplicate sunt respinse cu mesaj clar', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Pereche', 'first_name' => 'Dubla',
            'username' => 'pereche.dubla',
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'create',
            'teacher_fiche_sex' => Sex::Male->value,
            'teaching_pairs' => [
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
            ],
            'password' => 'Temp-Onboard-4',
        ])
        ->call('create')
        ->assertHasFormErrors(['teaching_pairs']);

    expect(User::query()->where('username', 'pereche.dubla')->exists())->toBeFalse();
});

it('fișa existentă se leagă fără să se creeze alta; alocările din flux se adaugă pe ea', function () {
    $fiche = Teacher::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Legare', 'first_name' => 'Fisa',
            'username' => 'legare.fisa',
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'link',
            'teacher_id' => $fiche->id,
            'teaching_pairs' => [
                ['school_class_id' => $this->classA->id, 'subject_id' => $this->math->id],
            ],
            'password' => 'Temp-Onboard-5',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'legare.fisa')->sole();

    expect(Teacher::query()->count())->toBe(1)
        ->and($fiche->fresh()->user_id)->toBe($user->id)
        ->and($fiche->fresh()->canGradeClassSubject($this->classA->id, $this->math->id))->toBeTrue();
});

it('la creare, fișa e OBLIGATORIE și în modul „fișă existentă" (fără fișă nu există cont pedagogic)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Fisa',
            'username' => 'fara.fisa',
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => 'link',
            'password' => 'Temp-Onboard-6',
        ])
        ->call('create')
        ->assertHasFormErrors(['teacher_id']);

    expect(User::query()->where('username', 'fara.fisa')->exists())->toBeFalse();
});

// ─── Elev: fișă nouă + cont + înmatriculare + părinți într-un singur flux ────────────────

it('onboarding elev: contul, fișa, înmatricularea și părinții se leagă împreună', function () {
    $parinte = User::factory()->create(['name' => 'Parinte Existent', 'username' => 'parinte.existent']);
    $parinte->assignRole(UserRole::Parinte->value);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Onboard', 'first_name' => 'Elevul',
            'username' => 'onboard.elev',
            'role' => UserRole::Elev->value,
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'student_fiche_register_number' => 'R-778',
            'enroll_class_id' => $this->classA->id,
            'student_guardian_user_ids' => [$parinte->id],
            'password' => 'Temp-Onboard-7',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'onboard.elev')->sole();
    $fiche = Student::query()->where('user_id', $user->id)->sole();

    expect($fiche->last_name)->toBe('Onboard')
        ->and($fiche->first_name)->toBe('Elevul')
        ->and($fiche->sex)->toBe(Sex::Male)
        ->and($fiche->register_number)->toBe('R-778');

    // Înmatricularea: clasa aleasă, anul derivat din clasă, cu data de azi.
    $enrollment = Enrollment::query()->where('student_id', $fiche->id)->sole();
    expect($enrollment->school_class_id)->toBe($this->classA->id)
        ->and($enrollment->academic_year_id)->toBe($this->year->id)
        ->and($enrollment->enrolled_on?->isToday())->toBeTrue();

    // Legătura cu părintele: pivotul guardian_student, din ambele direcții.
    expect($fiche->guardians()->pluck('users.id')->all())->toBe([$parinte->id])
        ->and($parinte->students()->pluck('students.id')->all())->toBe([$fiche->id]);
});

it('elevul NOU fără clasă nu trece: înmatricularea e parte din flux', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Clasa',
            'username' => 'fara.clasa',
            'role' => UserRole::Elev->value,
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Female->value,
            'password' => 'Temp-Onboard-8',
        ])
        ->call('create')
        ->assertHasFormErrors(['enroll_class_id']);

    expect(User::query()->where('username', 'fara.clasa')->exists())->toBeFalse()
        ->and(Student::query()->where('last_name', 'Fara')->exists())->toBeFalse();
});

it('elevul nou se creează complet FĂRĂ părinți; legătura se face ulterior, de pe contul părintelui', function () {
    // Fără dependență circulară elev↔părinte: câmpul „Părinții elevului" e OPȚIONAL.
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Fara', 'first_name' => 'Parinti',
            'username' => 'fara.parinti',
            'role' => UserRole::Elev->value,
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'enroll_class_id' => $this->classA->id,
            'password' => 'Temp-Onboard-10',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'fara.parinti')->sole();
    $fiche = Student::query()->where('user_id', $user->id)->sole();

    expect($fiche->guardians()->count())->toBe(0)
        ->and(Enrollment::query()->where('student_id', $fiche->id)->exists())->toBeTrue();

    // Drumul invers (propunerea beneficiarului): părintele — creat SAU editat ulterior —
    // își alege copiii dintre elevii existenți; asocierea se închide de pe partea lui.
    $parinte = User::factory()->create(['username' => 'parinte.ulterior']);
    $parinte->assignRole(UserRole::Parinte->value);

    Livewire::test(EditUser::class, ['record' => $parinte->getRouteKey()])
        ->fillForm(['guardian_student_ids' => [$fiche->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fiche->guardians()->pluck('users.id')->all())->toBe([$parinte->id]);
});

it('editarea contului de elev nu cere părinți și păstrează legăturile existente', function () {
    $parinte = User::factory()->create(['username' => 'parinte.pastrat']);
    $parinte->assignRole(UserRole::Parinte->value);

    // Elev complet: fișă + înmatriculare + părinte legat, din fluxul unificat.
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Editabil', 'first_name' => 'Elev',
            'username' => 'editabil.elev',
            'role' => UserRole::Elev->value,
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'enroll_class_id' => $this->classA->id,
            'student_guardian_user_ids' => [$parinte->id],
            'password' => 'Temp-Onboard-12',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'editabil.elev')->sole();
    $fiche = Student::query()->where('user_id', $user->id)->sole();

    // La EDITARE câmpul „Părinții elevului" nici nu apare (e pas de creare) — salvarea nu cere
    // nimic legat de părinți și NU pierde legăturile: tutorele, fișa și înmatricularea rămân.
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['first_name' => 'Elevul-Redenumit'])
        ->call('save')
        ->assertHasNoFormErrors();

    $fiche->refresh();
    expect($user->fresh()->name)->toBe('Editabil Elevul-Redenumit')
        ->and($fiche->user_id)->toBe($user->id)
        ->and($fiche->guardians()->pluck('users.id')->all())->toBe([$parinte->id])
        ->and(Enrollment::query()->where('student_id', $fiche->id)->where('school_class_id', $this->classA->id)->exists())->toBeTrue();
});

it('părintele se creează FĂRĂ copii (câmpul e opțional — copiii se pot lega și mai târziu)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Parinte', 'first_name' => 'Devreme',
            'username' => 'parinte.devreme',
            'role' => UserRole::Parinte->value,
            'password' => 'Temp-Onboard-11',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'parinte.devreme')->sole();

    expect($user->getRoleNames()->all())->toBe([UserRole::Parinte->value])
        ->and($user->students()->count())->toBe(0);
});

it('doar conturile cu rol de părinte pot fi legate drept părinți (id străin = respins)', function () {
    // Un cont de profesor nu e părinte — eticheta lipsește → selecția pică la validare.
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'last_name' => 'Parinti', 'first_name' => 'Filtrati',
            'username' => 'parinti.filtrati',
            'role' => UserRole::Elev->value,
            'student_fiche_mode' => 'create',
            'student_fiche_sex' => Sex::Male->value,
            'enroll_class_id' => $this->classA->id,
            'student_guardian_user_ids' => [$profesor->id],
            'password' => 'Temp-Onboard-9',
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(User::query()->where('username', 'parinti.filtrati')->exists())->toBeFalse();
});

// ─── Registrele nu mai creează fișe: butoanele duc în fluxul unificat ────────────────────

it('paginile directe de creare a fișelor sunt închise pentru toți (inclusiv director)', function () {
    $this->get('/admin/students/create')->assertForbidden();
    $this->get('/admin/teachers/create')->assertForbidden();
});

it('butoanele de adăugare din registre duc în fluxul de cont, cu rolul pre-completat', function () {
    Livewire::withQueryParams(['arhiva' => '1'])->test(ListStudents::class)
        ->assertActionVisible('create')
        ->assertActionHasUrl('create', UserResource::getUrl('create', ['rol' => UserRole::Elev->value]));

    Livewire::test(ListTeachers::class)
        ->assertActionVisible('create')
        ->assertActionHasUrl('create', UserResource::getUrl('create', ['rol' => UserRole::Profesor->value]));
});
