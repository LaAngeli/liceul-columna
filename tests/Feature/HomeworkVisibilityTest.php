<?php

/**
 * VIZIBILITATEA TEMELOR, aliniată la regula notelor (decizia beneficiarului, 01.08.2026).
 *
 * Până acum profesorul vedea temele oricărei CLASE unde preda, indiferent de disciplină — deci
 * conținutul colegilor (raportat: profesorul de matematică de la 1 A citea temele de română și
 * de istorie). Note/Absențe/Medii filtrau deja pe perechea (clasă, disciplină); temele erau
 * singura excepție. Aici se fixează noua regulă, pe cele trei ramuri.
 */

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\ActiveRole;
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

    // O clasă (1 A) cu două discipline predate de doi profesori diferiți — scenariul raportat.
    $this->class = SchoolClass::factory()->for($this->year)->create([
        'name' => 'I', 'grade_level' => 1, 'section' => 'A',
    ]);
    $this->math = Subject::factory()->create(['name' => 'Matematică']);
    $this->romanian = Subject::factory()->create(['name' => 'Limba și literatura română']);
});

/** Profesor cu fișă, alocat pe perechea (clasă, disciplină). */
function hwVisibilityTeacher(SchoolClass $class, Subject $subject, string $role = UserRole::Profesor->value): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);

    return $user;
}

/** Temă la o clasă (treaptă + literă), pe o disciplină, semnată de un profesor. */
function hwVisibilityFor(SchoolClass $class, Subject $subject, ?Teacher $author, ?string $section = null): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'grade_level' => $class->grade_level,
        // `section` explicit null = temă pe TOATĂ treapta.
        'section' => func_num_args() >= 4 ? $section : $class->section,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'teacher_id' => $author?->id,
    ]);
}

// ─── Regula centrală: disciplina, nu doar clasa ─────────────────────────────────────────

it('profesorul NU mai vede temele altei discipline din clasa lui (scurgerea raportată)', function () {
    $mathUser = hwVisibilityTeacher($this->class, $this->math);
    $romanianUser = hwVisibilityTeacher($this->class, $this->romanian);

    $mine = hwVisibilityFor($this->class, $this->math, $mathUser->teacher);
    $colleagues = hwVisibilityFor($this->class, $this->romanian, $romanianUser->teacher);

    actingAs($mathUser);

    $visible = HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all();

    expect($visible)->toContain($mine->id)
        ->and($visible)->not->toContain($colleagues->id);
});

it('profesorul vede tema COLEGULUI pe aceeași disciplină — grupele au doi profesori', function () {
    // Engleza se predă pe grupe: doi profesori, aceeași pereche (clasă, disciplină). Perimetrul
    // e al DISCIPLINEI, nu al persoanei — colegul de disciplină rămâne vizibil (52 de perechi
    // reale în registru sunt exact așa).
    $mine = hwVisibilityTeacher($this->class, $this->math);
    $colleague = hwVisibilityTeacher($this->class, $this->math);

    $theirs = hwVisibilityFor($this->class, $this->math, $colleague->teacher);

    actingAs($mine);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($theirs->id);
});

it('autorul își vede tema chiar după retragerea alocării — munca lui rămâne a lui', function () {
    $user = hwVisibilityTeacher($this->class, $this->math);
    $own = hwVisibilityFor($this->class, $this->math, $user->teacher);

    TeachingAssignment::query()->where('teacher_id', $user->teacher->id)->delete();

    actingAs($user);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$own->id]);
});

// ─── Temele pe TOATĂ TREAPTA: aceeași regulă de disciplină ──────────────────────────────

it('tema pe toată treapta se vede doar de cine predă acea disciplină în treaptă', function () {
    $mathUser = hwVisibilityTeacher($this->class, $this->math);
    $romanianUser = hwVisibilityTeacher($this->class, $this->romanian);

    // Temă „toată treapta 1" la matematică (fără literă), dată de administrație (fără autor).
    $wholeGrade = hwVisibilityFor($this->class, $this->math, null, null);

    actingAs($mathUser);
    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($wholeGrade->id);

    actingAs($romanianUser);
    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->not->toContain($wholeGrade->id);
});

// ─── Dirigintele: toată clasa lui, orice disciplină (ca la note) ────────────────────────

it('dirigintele vede toate temele clasei lui, indiferent de disciplină', function () {
    $homeroomUser = hwVisibilityTeacher($this->class, $this->math, UserRole::Diriginte->value);
    $this->class->update(['homeroom_teacher_id' => $homeroomUser->teacher->id]);

    $other = hwVisibilityTeacher($this->class, $this->romanian);
    $romanianHomework = hwVisibilityFor($this->class, $this->romanian, $other->teacher);

    actingAs($homeroomUser);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($romanianHomework->id);
});

it('sub multi-rol, contextul decide: Diriginte vede toată clasa, Profesor doar disciplina lui', function () {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Diriginte->value, UserRole::Profesor->value]);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    // Diriginte la 1 A, dar predă matematica la ALTĂ clasă din aceeași treaptă.
    $this->class->update(['homeroom_teacher_id' => $teacher->id]);
    $otherClass = SchoolClass::factory()->for($this->year)->create([
        'name' => 'I', 'grade_level' => 1, 'section' => 'B',
    ]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $otherClass->id, 'subject_id' => $this->math->id,
    ]);

    $colleague = hwVisibilityTeacher($this->class, $this->romanian);
    $romanianInMyClass = hwVisibilityFor($this->class, $this->romanian, $colleague->teacher);

    actingAs($user);

    // Context DIRIGINTE: clasa mea, orice disciplină → o vede.
    session()->put(ActiveRole::SESSION_KEY, UserRole::Diriginte->value);
    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($romanianInMyClass->id);

    // Context PROFESOR: puterile de dirigenție sunt STINSE (F3) → rămâne doar disciplina lui.
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);
    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->not->toContain($romanianInMyClass->id);
});

// ─── Administrația nu e atinsă ──────────────────────────────────────────────────────────

it('administrația vede în continuare toate temele', function () {
    $author = hwVisibilityTeacher($this->class, $this->romanian);
    $homework = hwVisibilityFor($this->class, $this->romanian, $author->teacher);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    actingAs($director);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($homework->id);
});
