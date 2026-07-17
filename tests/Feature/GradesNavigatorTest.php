<?php

/**
 * Navigatorul de catalog al paginii „Note" (meniu drill-down în locul listei plate):
 * dimensiuni pe rol, carduri scoped, context aplicat pe tabel, chips, validarea id-urilor
 * din URL împotriva scope-ului și pre-completarea formularului de creare din context.
 */

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
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
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    // Două clase în anul curent: profesorul predă doar în prima.
    $this->ownClass = SchoolClass::factory()->for($this->year)->create(['name' => 'NAV-A', 'section' => null]);
    $this->foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'NAV-B', 'section' => null]);
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);

    $this->ownStudent = Student::factory()->create();
    Enrollment::factory()->for($this->ownStudent)->for($this->ownClass)->for($this->year)->create();
    $this->foreignStudent = Student::factory()->create();
    Enrollment::factory()->for($this->foreignStudent)->for($this->foreignClass)->for($this->year)->create();
});

/** Profesor cu alocare pe (clasă, disciplină). */
function navigatorTeacher(SchoolClass $class, Subject $subject, ?SchoolClass $homeroom = null): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    if ($homeroom !== null) {
        $homeroom->update(['homeroom_teacher_id' => $teacher->id]);
    }

    return $user;
}

function navigatorDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

function navigatorGrade(Student $student, SchoolClass $class, Subject $subject, Term $term, ?Teacher $teacher = null, string $on = '2025-10-10'): Grade
{
    return Grade::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'teacher_id' => $teacher?->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 8,
        'graded_on' => $on,
    ]);
}

// ─── Bara temporală (2026-07-18): aceeași logică ca la Teme, pe data acordării ────────────

it('bara temporală filtrează notele pe data acordării; intervalul liber acoperă perioade arbitrare', function () {
    actingAs(navigatorDirector());

    $inWeek = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term, on: '2025-10-08');
    $outsideWeek = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term, on: '2025-10-20');

    // Modul „săptămână" pe referința 2025-10-06 (săptămâna 6–12 oct).
    Livewire::withQueryParams(['mod' => 'saptamana', 'ref' => '2025-10-06'])
        ->test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$inWeek])
        ->assertCanNotSeeTableRecords([$outsideWeek]);

    // Mod invalid din URL → „Toate" (ambele vizibile).
    Livewire::withQueryParams(['mod' => 'decada'])
        ->test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$inWeek, $outsideWeek]);

    // Filtrul interval De la / Până la — independent de bară.
    Livewire::test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->filterTable('interval', ['from' => '2025-10-15', 'until' => '2025-10-25'])
        ->assertCanSeeTableRecords([$outsideWeek])
        ->assertCanNotSeeTableRecords([$inWeek]);
});

// ─── Meniul de dimensiuni pe rol ─────────────────────────────────────────────────────────

it('profesorul NU vede dimensiunea „Profesori"; administrația o vede', function () {
    actingAs(navigatorTeacher($this->ownClass, $this->subject));
    $teacherDimensions = Livewire::test(ListGrades::class)->instance()->catalogDimensions();
    expect($teacherDimensions)->not->toHaveKey('profesori')
        ->and($teacherDimensions)->toHaveKeys(['clase', 'discipline', 'perioade']);

    actingAs(navigatorDirector());
    expect(Livewire::test(ListGrades::class)->instance()->catalogDimensions())->toHaveKey('profesori');
});

it('cardurile de clase ale profesorului conțin DOAR clasele lui', function () {
    actingAs(navigatorTeacher($this->ownClass, $this->subject));

    $cards = Livewire::test(ListGrades::class)->instance()->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->ownClass->id]);
});

it('administrația vede cardurile tuturor claselor anului curent', function () {
    actingAs(navigatorDirector());

    $cards = Livewire::test(ListGrades::class)->instance()->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())
        ->toContain($this->ownClass->id)
        ->toContain($this->foreignClass->id);
});

// ─── Contextul restrânge tabelul (peste scope-ul existent) ───────────────────────────────

it('deschiderea unei clase restrânge tabelul la notele ei', function () {
    actingAs(navigatorDirector());

    $ownGrade = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term);
    $foreignGrade = navigatorGrade($this->foreignStudent, $this->foreignClass, $this->subject, $this->term);

    Livewire::test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$ownGrade])
        ->assertCanNotSeeTableRecords([$foreignGrade]);
});

it('id de clasă din AFARA scope-ului, venit prin URL, nu deschide context (navigator afișat)', function () {
    actingAs(navigatorTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['clasa' => (string) $this->foreignClass->id])
        ->test(ListGrades::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse();
    // Navigatorul rămâne pe ecran (nu s-a deschis niciun context pe clasa străină).
    $component->assertSee('NAV-A')->assertDontSee('NAV-B');
});

it('contextul de perioadă (semestru) restrânge tabelul', function () {
    actingAs(navigatorDirector());

    $otherTerm = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-02-01', 'ends_on' => '2026-06-30', 'is_current' => false,
    ]);

    $inTerm = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term);
    $outTerm = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $otherTerm);

    Livewire::test(ListGrades::class)
        ->call('setCatalogDimension', 'perioade')
        ->call('openCatalogEntity', $this->term->id)
        ->assertCanSeeTableRecords([$inTerm])
        ->assertCanNotSeeTableRecords([$outTerm]);
});

it('chip-ul de disciplină restrânge suplimentar contextul clasei', function () {
    actingAs(navigatorDirector());

    $otherSubject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $mathGrade = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term);
    $otherGrade = navigatorGrade($this->ownStudent, $this->ownClass, $otherSubject, $this->term);

    Livewire::test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->call('setCatalogChip', $this->subject->id)
        ->assertCanSeeTableRecords([$mathGrade])
        ->assertCanNotSeeTableRecords([$otherGrade]);
});

// ─── Diriginte: clasa lui = vede tot; chips-urile includ disciplinele altora ─────────────

it('dirigintele vede în clasa lui și disciplinele altor profesori (badge + chips)', function () {
    $user = navigatorTeacher($this->ownClass, $this->subject, homeroom: $this->ownClass);

    // Alt profesor predă altă disciplină în aceeași clasă.
    $otherSubject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $otherTeacherUser = navigatorTeacher($this->ownClass, $otherSubject);

    actingAs($user);

    $component = Livewire::test(ListGrades::class);
    $cards = $component->instance()->catalogEntityCards();

    expect(collect($cards)->firstWhere('id', $this->ownClass->id)['badge'])->not->toBeNull();

    $chips = $component->call('openCatalogEntity', $this->ownClass->id)->instance()->catalogChips();

    expect(collect($chips)->pluck('id')->all())
        ->toContain($this->subject->id)
        ->toContain($otherSubject->id);
});

it('profesorul (ne-diriginte) primește chips DOAR pentru disciplinele lui în clasă', function () {
    $otherSubject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    navigatorTeacher($this->ownClass, $otherSubject); // colegul predă altceva în aceeași clasă

    actingAs(navigatorTeacher($this->ownClass, $this->subject));

    $chips = Livewire::test(ListGrades::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->instance()
        ->catalogChips();

    expect(collect($chips)->pluck('id')->all())
        ->toContain($this->subject->id)
        ->not->toContain($otherSubject->id);
});

// ─── Dimensiunea „Profesori" (administrație) ─────────────────────────────────────────────

it('cardurile de profesori agregă notele pe autor, iar contextul restrânge tabelul', function () {
    actingAs(navigatorDirector());

    $teacherUser = navigatorTeacher($this->ownClass, $this->subject);
    $teacher = $teacherUser->teacher;

    $byTeacher = navigatorGrade($this->ownStudent, $this->ownClass, $this->subject, $this->term, $teacher);
    $byNobody = navigatorGrade($this->foreignStudent, $this->foreignClass, $this->subject, $this->term);

    $component = Livewire::test(ListGrades::class)->call('setCatalogDimension', 'profesori');

    expect(collect($component->instance()->catalogEntityCards())->pluck('id')->all())->toBe([$teacher->id]);

    $component->call('openCatalogEntity', $teacher->id)
        ->assertCanSeeTableRecords([$byTeacher])
        ->assertCanNotSeeTableRecords([$byNobody]);
});

// ─── Pre-completarea formularului din context ────────────────────────────────────────────

it('formularul de creare se pre-completează din contextul navigatorului (clasă + disciplină)', function () {
    actingAs(navigatorTeacher($this->ownClass, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->ownClass->id, 'disciplina' => (string) $this->subject->id])
        ->test(CreateGrade::class)
        ->assertFormSet([
            'school_class_id' => $this->ownClass->id,
            'subject_id' => $this->subject->id,
        ]);
});

it('un id de clasă din afara scope-ului NU se pre-completează în formular', function () {
    actingAs(navigatorTeacher($this->ownClass, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->foreignClass->id])
        ->test(CreateGrade::class)
        ->assertFormSet([
            'school_class_id' => null,
        ]);
});
