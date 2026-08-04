<?php

/**
 * HARTA COMPLETĂ A CLASEI PENTRU DIRIGINTE (cerință beneficiar, 04.08.2026).
 *
 * Spec §3.2 îi dă dirigintelui „vizualizare completă (citire) pe toate disciplinele clasei sale —
 * fără drept de a modifica notele colegilor". În practică, jumătatea de sus lipsea: filtrele de
 * disciplină se făceau peste tot pe `taughtSubjectIds()`, adică pe ce predă EL, așa că exact
 * disciplinele colegilor — cele pentru care are nevoie de imaginea de ansamblu — rămâneau ascunse.
 *
 * Testele apără AMBELE jumătăți deodată, pentru că una fără cealaltă ar fi o regresie tăcută:
 * ce se deschide (vizualizare + solicitare de corecție + rapoarte pe clasa lui) și ce rămâne
 * închis (scrierea și anularea notelor pe disciplina altuia).
 */

use App\Enums\StaffReportType;
use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
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
use App\Support\ActiveRole;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year, 'academicYear')->create([
        'number' => 2,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-06-30',
        'is_current' => true,
    ]);
    $this->gradedOn = '2026-06-30';

    $this->homeroomClass = SchoolClass::factory()->for($this->year)->create(['name' => 'IX', 'section' => 'A', 'grade_level' => 9]);
    $this->otherClass = SchoolClass::factory()->for($this->year)->create(['name' => 'VI', 'section' => 'B', 'grade_level' => 6]);

    $this->mySubject = Subject::factory()->create(['name' => 'Limba română']);
    $this->colleagueSubject = Subject::factory()->create(['name' => 'Fizică']);
    $this->foreignSubject = Subject::factory()->create(['name' => 'Geografie']);

    // Dirigintele: conduce IX A și predă acolo o singură disciplină.
    $this->user = User::factory()->create();
    $this->user->assignRole(UserRole::Diriginte->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $this->teacher->id]);

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->homeroomClass->id,
        'subject_id' => $this->mySubject->id,
    ]);

    // Colegul: predă fizica la ACEEAȘI clasă. Disciplina lui e miezul cerinței.
    $this->colleague = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->colleague->id,
        'school_class_id' => $this->homeroomClass->id,
        'subject_id' => $this->colleagueSubject->id,
    ]);

    // Disciplină dintr-o clasă STRĂINĂ — martorul că harta se oprește la clasa lui.
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->colleague->id,
        'school_class_id' => $this->otherClass->id,
        'subject_id' => $this->foreignSubject->id,
    ]);
});

// ── Sursa unică ────────────────────────────────────────────────────────────────────────────────

it('adună disciplinele clasei de dirigenție, indiferent cine le predă', function () {
    expect($this->teacher->homeroomSubjectIds())
        ->toContain($this->mySubject->id)
        ->toContain($this->colleagueSubject->id)
        ->not->toContain($this->foreignSubject->id);
});

it('separă disciplinele pe contextul pedagogic activ', function () {
    // Contul e multi-rol (are și profesor prin repartizare), deci contextul chiar comută.
    $this->user->assignRole(UserRole::Profesor->value);
    $user = $this->user->fresh();
    actingAs($user);

    session()->put(ActiveRole::SESSION_KEY, UserRole::Diriginte->value);
    expect($user->contextSubjectIds())
        ->toContain($this->mySubject->id)
        ->toContain($this->colleagueSubject->id);

    // Ca PROFESOR rămâne strict disciplina lui — comutarea de context nu e decorativă.
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);
    expect($user->contextSubjectIds())
        ->toContain($this->mySubject->id)
        ->not->toContain($this->colleagueSubject->id);
});

// ── Ce se DESCHIDE ─────────────────────────────────────────────────────────────────────────────

it('vede în „Discipline" și disciplina predată de coleg la clasa lui', function () {
    actingAs($this->user->fresh());

    $visible = SubjectResource::getEloquentQuery()->pluck('id');

    expect($visible)
        ->toContain($this->mySubject->id)
        ->toContain($this->colleagueSubject->id)
        ->not->toContain($this->foreignSubject->id);
});

it('vede disciplina colegului pe ECRANUL „Discipline", nu doar în interogarea resursei', function () {
    /**
     * REGRESIE PRINSĂ LA VERIFICAREA LIVE (04.08.2026): scoping-ul corectat pe
     * `SubjectResource::getEloquentQuery()` NU ajungea aici. Pagina pentru cadre nu randează tabelul
     * resursei, ci carduri construite din propria interogare — două căi paralele peste aceleași date,
     * iar corectarea uneia nu se vedea în cealaltă. Testul se uită la ce vede omul pe ecran.
     */
    actingAs($this->user->fresh());

    $titluri = collect(Livewire::test(ListSubjects::class)->instance()->subjectCards())->pluck('title');

    expect($titluri)
        ->toContain($this->mySubject->name)
        ->toContain($this->colleagueSubject->name)
        ->not->toContain($this->foreignSubject->name);
});

it('în context PROFESOR ecranul revine la disciplinele proprii', function () {
    // Cealaltă față a aceleiași reguli: dacă lărgirea ar fi scăpat de sub context, profesorul ar
    // fi început să vadă orele colegilor — exact scurgerea pe care scoping-ul o previne.
    $this->user->assignRole(UserRole::Profesor->value);
    actingAs($this->user->fresh());
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);

    $titluri = collect(Livewire::test(ListSubjects::class)->instance()->subjectCards())->pluck('title');

    expect($titluri)
        ->toContain($this->mySubject->name)
        ->not->toContain($this->colleagueSubject->name);
});

it('poate deschide rapoartele pe disciplina colegului, la clasa lui', function () {
    $user = $this->user->fresh();
    actingAs($user);

    expect(StaffReportType::ClassSubjectSituation->canGenerate($user, $this->homeroomClass->id, $this->colleagueSubject->id))->toBeTrue()
        ->and(StaffReportType::GradeDistribution->canGenerate($user, $this->homeroomClass->id, $this->colleagueSubject->id))->toBeTrue()
        // Perimetrul se oprește la clasa lui: altă clasă, aceeași disciplină → nu.
        ->and(StaffReportType::ClassSubjectSituation->canGenerate($user, $this->otherClass->id, $this->foreignSubject->id))->toBeFalse();
});

it('poate SOLICITA o corecție pe nota pusă de coleg — canalul cu urmă în catalog', function () {
    $grade = homeroomMapColleagueGrade($this);

    actingAs($this->user->fresh());

    Livewire::test(ListGrades::class)
        ->assertTableActionVisible('requestCorrection', $grade);
});

// ── Ce rămâne ÎNCHIS (spec §3.2: „fără drept de a modifica notele colegilor") ───────────────────

it('NU poate anula nota colegului — anularea o scoate din medii', function () {
    $grade = homeroomMapColleagueGrade($this);

    actingAs($this->user->fresh());

    Livewire::test(ListGrades::class)
        ->assertTableActionHidden('annul', $grade);
});

it('NU poate pune notă la disciplina colegului, nici cu un POST trimis direct', function () {
    $student = homeroomMapStudent($this);

    actingAs($this->user->fresh());

    $page = new class
    {
        use EnforcesGradeScope;

        /** @param array<string, mixed> $data */
        public function run(array $data): array
        {
            return $this->enforceGradeScope($data);
        }
    };

    try {
        $page->run([
            'student_id' => $student->id,
            'school_class_id' => $this->homeroomClass->id,
            'subject_id' => $this->colleagueSubject->id,
            'graded_on' => $this->gradedOn,
            'value' => 9,
        ]);

        $this->fail('Nota la disciplina colegului ar fi trebuit refuzată pe server.');
    } catch (ValidationException $exception) {
        // Cheia contează: dovedește că refuzul vine din SCOPE, nu dintr-o dată invalidă care ar
        // fi făcut testul să treacă din alt motiv.
        expect(array_keys($exception->errors()))->toContain('data.subject_id');
    }
});

/** Elev înscris în clasa de dirigenție. */
function homeroomMapStudent(object $ctx): Student
{
    $student = Student::factory()->create();

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $ctx->homeroomClass->id,
        'academic_year_id' => $ctx->year->id,
    ]);

    return $student;
}

/** Notă pusă de COLEG, la disciplina lui, unui elev din clasa de dirigenție. */
function homeroomMapColleagueGrade(object $ctx): Grade
{
    return Grade::factory()->create([
        'student_id' => homeroomMapStudent($ctx)->id,
        'school_class_id' => $ctx->homeroomClass->id,
        'subject_id' => $ctx->colleagueSubject->id,
        'teacher_id' => $ctx->colleague->id,
        'term_id' => $ctx->term->id,
        'graded_on' => $ctx->gradedOn,
    ]);
}
