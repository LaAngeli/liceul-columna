<?php

/**
 * Cluster Foaie matricolă + nomenclatoare (mapare + verificare adversarială, 2026-07-11).
 * Trei reguli pe care interacțiunile între roluri le cer de la nomenclatoare:
 *
 *  1. ISTORICUL SUPRAVIEȚUIEȘTE ARHIVĂRII: soft-delete pe o disciplină/clasă/elev nu are voie să
 *     lase notele/mediile/cabinetul cu părinți null (crash 500 la familie sau în triaj).
 *  2. FORCEDELETE NU DISTRUGE ARHIVA: FK-urile `grades` sunt cascadeOnDelete → ștergerea permanentă
 *     a unui nomenclator cu istoric ar șterge definitiv note (încalcă §1). Blocat prin policy cât
 *     timp există istoric dependent; rândurile create din greșeală (fără date) rămân curățabile.
 *  3. FĂRĂ SEMESTRU CURENT contorul „Corigenți" arată 0, nu toți elevii școlii (scope-ul
 *     corigentInTerm(null) e neutru — corect pentru filtru, dar contorul cere gardă explicită).
 */

use App\Actions\DetermineStudentStatus;
use App\Enums\CorigentaSeason;
use App\Enums\UserRole;
use App\Filament\Widgets\NeedsAttention;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->subject = Subject::factory()->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

// ─── 1. Istoricul supraviețuiește arhivării nomenclatoarelor ─────────────────────────────

it('cabinetul familiei se randează și după arhivarea disciplinei și a clasei elevului', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $user->id]);
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();
    Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 8,
    ]);

    // Configuratorul arhivează disciplina ȘI clasa — istoricul elevului nu dispare și nu crapă.
    $this->subject->delete();
    $this->class->delete();

    actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('cabinet.student', $student))
        ->assertOk();
});

it('triajul statutului numește disciplina restantă și dacă disciplina a fost arhivată', function () {
    $student = Student::factory()->create();
    TermAverage::factory()->create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 3.00,
    ]);

    $this->subject->delete();

    $result = app(DetermineStudentStatus::class)->forTerm($student->id, $this->term->id);

    expect($result['failingSubjects'])->toBe([$this->subject->name]);
});

it('nota de corigență intră în matricolă și când clasa sau înmatricularea au fost arhivate', function () {
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    // Elevul s-a transferat: înmatricularea și clasa au fost arhivate ÎNTRE timp.
    $enrollment->delete();
    $this->class->delete();

    CorigentaExam::create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara, 'mark' => 6.00,
    ]);

    expect(AcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('subject_id', $this->subject->id)
        ->where('grade_level', $this->class->grade_level)
        ->where('value', 6.00)
        ->exists())->toBeTrue();
});

it('fără NICIO înmatriculare, nota de corigență rămâne pe examen fără să crape (skip avertizat)', function () {
    $student = Student::factory()->create();

    CorigentaExam::create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara, 'mark' => 6.00,
    ]);

    // Nota trăiește pe examen; matricola nu are rând (treaptă nedeterminabilă) — dar fără excepție.
    expect(CorigentaExam::query()->where('mark', 6.00)->exists())->toBeTrue()
        ->and(AcademicRecord::query()->count())->toBe(0);
});

// ─── 2. ForceDelete blocat cât timp există istoric academic dependent ─────────────────────

it('ForceDelete e refuzat pe nomenclatoarele cu istoric academic, dar permis pe cele goale', function () {
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();
    Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 8,
    ]);

    // Cu istoric → refuzat (cascada FK ar distruge note — §1).
    expect(Gate::forUser($this->director)->check('forceDelete', $this->subject))->toBeFalse()
        ->and(Gate::forUser($this->director)->check('forceDelete', $this->class))->toBeFalse()
        ->and(Gate::forUser($this->director)->check('forceDelete', $student))->toBeFalse()
        ->and(Gate::forUser($this->director)->check('forceDelete', $this->term))->toBeFalse()
        ->and(Gate::forUser($this->director)->check('forceDelete', $this->year))->toBeFalse();

    // Rândurile create din greșeală (fără istoric) rămân curățabile de configuratori.
    expect(Gate::forUser($this->director)->check('forceDelete', Subject::factory()->create()))->toBeTrue()
        ->and(Gate::forUser($this->director)->check('forceDelete', Student::factory()->create()))->toBeTrue()
        ->and(Gate::forUser($this->director)->check('forceDelete', AcademicYear::factory()->create()))->toBeTrue();
});

it('blocajul ForceDelete vede și istoricul soft-deleted (cascada l-ar distruge și pe el)', function () {
    $student = Student::factory()->create();
    $grade = Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 8,
    ]);

    $grade->delete();

    expect(Gate::forUser($this->director)->check('forceDelete', $this->subject))->toBeFalse();
});

// ─── 3. Contorul „Corigenți" fără semestru curent ────────────────────────────────────────

it('fără semestru curent, contorul Corigenți arată 0 — nu toți elevii școlii', function () {
    // Elev cu medie restantă într-un semestru care NU mai e curent.
    $student = Student::factory()->create();
    TermAverage::factory()->create([
        'student_id' => $student->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 3.00,
    ]);
    $this->term->update(['is_current' => false]);

    actingAs($this->director);
    NeedsAttention::flushCache();

    Livewire::test(NeedsAttention::class)
        ->assertViewHas('items', function (array $items): bool {
            $corigenti = collect($items)->firstWhere('label', __('panel.widgets.director_overview.corigenti'));

            return $corigenti !== null && $corigenti['count'] === 0;
        });
});
