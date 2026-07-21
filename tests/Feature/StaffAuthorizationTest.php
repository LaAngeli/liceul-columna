<?php

/**
 * Regresii dovedite în auditul live al panoului staff (10.07.2026, `raport-sinteza-staff.md`).
 *
 * Cele două CRITICE: profesorul rescria valoarea unei note direct din pagina de editare, și ștergea
 * DEFINITIV date de catalog prin „Ștergerea forțată" de pe înregistrarea din coș. Ambele treceau
 * pentru că Filament v4 autorizează ACȚIUNILE prin policy (Gate), nu prin overrides-urile statice
 * `Resource::canEdit()` — care gate-uiesc doar pagina. Testele de mai jos apără policy-urile.
 */

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\EditAbsence;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    $this->profesor = User::factory()->create();
    $this->profesor->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

function auditGrade(mixed $ctx): Grade
{
    return Grade::factory()->create([
        'student_id' => $ctx->student->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => $ctx->subject->id,
        'term_id' => $ctx->term->id,
        'teacher_id' => $ctx->teacher->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 9,
        'calificativ' => null,
    ]);
}

function auditAbsence(mixed $ctx, ?int $teacherId = null): Absence
{
    return Absence::factory()->create([
        'student_id' => $ctx->student->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => $ctx->subject->id,
        'term_id' => $ctx->term->id,
        'teacher_id' => $teacherId,
        'occurred_on' => '2026-03-10',
    ]);
}

// ─── CRITIC #1: valoarea notei nu se rescrie direct ─────────────────────────────────────

it('profesorul nu poate deschide pagina de editare a unei note', function () {
    $grade = auditGrade($this);

    actingAs($this->profesor)
        ->get("/admin/grades/{$grade->id}/edit")
        ->assertForbidden();
});

it('profesorul nu are dreptul de a modifica valoarea unei note, nici prin Gate', function () {
    $grade = auditGrade($this);

    expect(Gate::forUser($this->profesor)->check('update', $grade))->toBeFalse();
    expect(Gate::forUser($this->director)->check('update', $grade))->toBeTrue();
});

it('administrația academică poate deschide pagina de editare a notei', function () {
    $grade = auditGrade($this);

    actingAs($this->director)
        ->get("/admin/grades/{$grade->id}/edit")
        ->assertOk();
});

it('nota nu se șterge niciodată, nici de administrație', function () {
    $grade = auditGrade($this);

    expect(Gate::forUser($this->director)->check('delete', $grade))->toBeFalse();
    expect(Gate::forUser($this->director)->check('forceDelete', $grade))->toBeFalse();
});

it('după crearea unei note, profesorul revine la listă (nu pe pagina de editare)', function () {
    actingAs($this->profesor);

    Livewire::test(CreateGrade::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'evaluation_type' => EvaluationType::Curenta->value,
            'graded_on' => '2026-03-10',
            'value' => 8,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/grades');
});

// ─── CRITIC #2: ștergerea permanentă a datelor de catalog ───────────────────────────────

it('profesorul nu poate șterge DEFINITIV o absență, nici pe a lui', function () {
    $absence = auditAbsence($this, $this->teacher->id);

    expect(Gate::forUser($this->profesor)->check('forceDelete', $absence))->toBeFalse();
    expect(Gate::forUser($this->profesor)->check('restore', $absence))->toBeFalse();
});

it('acțiunile de ștergere forțată și restaurare nu apar profesorului pe pagina de editare', function () {
    $absence = auditAbsence($this, $this->teacher->id);
    $absence->delete();

    // Acțiunile de înregistrare stau pe rândul butoanelor formularului, nu în antet
    // ({@see App\Filament\Concerns\PlacesRecordActionsWithForm}) → localizare prin schemaComponent.
    // Într-o schemă, componenta ASCUNSĂ nici nu se montează, deci proba are două jumătăți:
    // acțiunile există (administrația le vede) și îi lipsesc profesorului.
    $forceDelete = fn (): TestAction => TestAction::make('forceDelete')->schemaComponent('form-actions', schema: 'content');
    $restore = fn (): TestAction => TestAction::make('restore')->schemaComponent('form-actions', schema: 'content');

    actingAs($this->director);

    Livewire::test(EditAbsence::class, ['record' => $absence->getKey()])
        ->assertActionExists($forceDelete())
        ->assertActionExists($restore());

    actingAs($this->profesor);

    Livewire::test(EditAbsence::class, ['record' => $absence->getKey()])
        ->assertActionDoesNotExist($forceDelete())
        ->assertActionDoesNotExist($restore());
});

it('administrația academică poate șterge definitiv și restaura o absență', function () {
    $absence = auditAbsence($this, $this->teacher->id);

    expect(Gate::forUser($this->director)->check('forceDelete', $absence))->toBeTrue();
    expect(Gate::forUser($this->director)->check('restore', $absence))->toBeTrue();
});

it('profesorul retrage absențele lui și pe cele fără autor din scope, nu pe ale colegilor', function () {
    $altProfesor = User::factory()->create();
    $altProfesor->assignRole(UserRole::Profesor->value);
    $altTeacher = Teacher::factory()->create(['user_id' => $altProfesor->id]);

    $aMea = auditAbsence($this, $this->teacher->id);
    $legacy = auditAbsence($this, null);
    $aColegului = auditAbsence($this, $altTeacher->id);

    expect(Gate::forUser($this->profesor)->check('delete', $aMea))->toBeTrue();
    expect(Gate::forUser($this->profesor)->check('delete', $legacy))->toBeTrue();
    expect(Gate::forUser($this->profesor)->check('delete', $aColegului))->toBeFalse();
});

it('autorul unei teme o poate retrage, dar nu o poate șterge definitiv', function () {
    $homework = HomeworkAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subject->id,
        'grade_level' => $this->class->grade_level,
    ]);

    expect(Gate::forUser($this->profesor)->check('delete', $homework))->toBeTrue();
    expect(Gate::forUser($this->profesor)->check('forceDelete', $homework))->toBeFalse();
    expect(Gate::forUser($this->profesor)->check('restore', $homework))->toBeFalse();
});

// ─── TEME: editarea directă a dispărut pentru profesor (regula 2026-07-15) ────────────────
// Profesorii văd temele claselor comune (by design), dar NU mai editează direct nicio temă —
// nici pe a lor: corectarea trece prin „Solicită corecție" (vizibilă DOAR autorului), cu
// aprobarea Dir / PVD / AO. Editarea rămâne acțiunea exclusivă a aprobatorilor.
it('profesorul nu vede „Editare" pe nicio temă; „Solicită corecție" apare doar pe a sa', function () {
    $colleagueUser = User::factory()->create();
    $colleagueUser->assignRole(UserRole::Profesor->value);
    $colleague = Teacher::factory()->create(['user_id' => $colleagueUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $colleague->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // section = null → tema se vede la orice clasă de acea treaptă (scope), deci și colegul o vede.
    $ownHomework = HomeworkAssignment::factory()->create([
        'teacher_id' => $colleague->id, 'subject_id' => $this->subject->id,
        'grade_level' => $this->class->grade_level, 'section' => null,
    ]);
    $othersHomework = HomeworkAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id,
        'grade_level' => $this->class->grade_level, 'section' => null,
    ]);

    actingAs($colleagueUser);

    // Pagina Teme e acum navigator drill-down: deschidem contextul clasei ca să vedem tabelul.
    Livewire::test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertCanSeeTableRecords([$ownHomework, $othersHomework])
        ->assertTableActionHidden('edit', $ownHomework)
        ->assertTableActionHidden('edit', $othersHomework)
        ->assertTableActionVisible('requestCorrection', $ownHomework)
        ->assertTableActionHidden('requestCorrection', $othersHomework);

    // Aprobatorul (AO) păstrează editarea directă.
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    Livewire::test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->class->id)
        ->assertTableActionVisible('edit', $ownHomework);
});

// ─── SISTEMIC: nomenclatoarele nu se scriu de profesor ──────────────────────────────────

it('profesorul nu poate crea sau modifica nomenclatoarele școlii', function (string $model) {
    /** @var class-string<Model> $model */
    $record = $model::query()->first() ?? $model::factory()->create();

    expect(Gate::forUser($this->profesor)->check('create', $model))->toBeFalse();
    expect(Gate::forUser($this->profesor)->check('update', $record))->toBeFalse();
    expect(Gate::forUser($this->profesor)->check('delete', $record))->toBeFalse();
    expect(Gate::forUser($this->profesor)->check('forceDelete', $record))->toBeFalse();
})->with([
    Student::class,
    Subject::class,
    SchoolClass::class,
]);

it('profesorul primește 403 pe paginile de creare din nomenclatoare', function (string $url) {
    actingAs($this->profesor)->get($url)->assertForbidden();
})->with([
    '/admin/students/create',
    '/admin/subjects/create',
    '/admin/school-classes/create',
]);

/**
 * Butoanele care duc în 403 sunt bug-ul sistemic din audit: `Resource::canCreate()/canEdit()`
 * gate-uiesc PAGINA, dar acțiunile își cer autorizarea de la Gate. Fără metodă în policy,
 * Filament le permite. Aserțiile pe Gate NU prind asta — doar cele pe vizibilitatea acțiunii.
 */
it('profesorul nu vede butonul de adăugare în nomenclatoare', function (string $page) {
    actingAs($this->profesor);

    Livewire::test($page)->assertActionHidden('create');
})->with([
    ListStudents::class,
    ListSubjects::class,
    ListSchoolClasses::class,
]);

it('profesorul nu vede acțiunea de editare pe rândurile nomenclatoarelor', function () {
    actingAs($this->profesor);

    Livewire::test(ListSubjects::class)
        ->assertTableActionHidden('edit', $this->subject);
});

it('configuratorul școlii vede butonul de adăugare și acțiunea de editare', function () {
    actingAs($this->director);

    Livewire::test(ListSubjects::class)
        ->assertActionVisible('create')
        ->assertTableActionVisible('edit', $this->subject);
});

it('foaia matricolă e arhivă: nu se scrie de nimeni', function () {
    $record = AcademicRecord::factory()->create(['student_id' => $this->student->id]);

    foreach ([$this->profesor, $this->director] as $user) {
        expect(Gate::forUser($user)->check('create', AcademicRecord::class))->toBeFalse();
        expect(Gate::forUser($user)->check('update', $record))->toBeFalse();
        expect(Gate::forUser($user)->check('delete', $record))->toBeFalse();
    }
});
