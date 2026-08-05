<?php

/**
 * SCALA CALIFICATIVELOR (cerința beneficiarului, 05.08.2026).
 *
 * Câmpul „Nota propusă" dintr-o cerere de corecție era text liber („cel mult 10 caractere"), așa
 * că în catalog a intrat literalmente „test Calif" — un simbol care nu se poate agrega, traduce
 * sau compara între elevi. Testele de aici fixează promisiunea inversă:
 *
 *  - calificativul e un SIMBOL dintr-o scală închisă ({@see Calificativ}), aceeași în toate cele
 *    patru locuri de unde se poate cere o corecție și la introducerea directă;
 *  - garda stă pe MODEL, deci prinde și calea ocolită — aprobarea unei cereri scrie în notă;
 *  - dar NU pedepsește retroactiv: cele două rânduri istorice cu simbol greșit rămân editabile
 *    pe celelalte câmpuri;
 *  - propunerea numerică respectă aceeași scală ca nota însăși: întreg 1–10, nu 6,5.
 */

use App\Enums\Calificativ;
use App\Enums\CorrectionStatus;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 2]);

    // Disciplină pe CALIFICATIV — acolo trăiește câmpul în cauză.
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Calificativ]);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $this->teacherUser = $teacherUser->fresh();

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->student = Student::factory()->create(['last_name' => 'Vulpe', 'first_name' => 'Elev']);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create([
        'enrolled_on' => Carbon::today()->subMonths(3),
        'left_on' => null,
    ]);
});

/** Notă pe calificativ în clasa de test. */
function calificativGrade(object $ctx, string $simbol): Grade
{
    return Grade::query()->create([
        'student_id' => $ctx->student->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => $ctx->subject->id,
        'teacher_id' => $ctx->teacher->id,
        'term_id' => $ctx->term->id,
        'graded_on' => Carbon::today()->subDays(3)->toDateString(),
        'type' => 1,
        'evaluation_type' => 'curenta',
        'value' => null,
        'calificativ' => $simbol,
    ]);
}

it('scala are exact cele șase simboluri ale școlii, în ambele grupe', function () {
    expect(Calificativ::values())->toBe(['FB', 'B', 'S', 'i', 'g', 'sp']);

    $grupe = Calificativ::groupedOptions();

    expect($grupe)->toHaveCount(2)
        ->and(array_keys(array_merge(...array_values($grupe))))
        ->toBe(['FB', 'B', 'S', 'i', 'g', 'sp']);
});

it('modelul REFUZĂ un simbol inventat, oricare ar fi calea de scriere', function () {
    expect(fn () => calificativGrade($this, 'test Calif'))->toThrow(ValidationException::class);

    expect(Grade::query()->count())->toBe(0);
});

it('modelul acceptă fiecare simbol al scalei', function () {
    foreach (Calificativ::cases() as $case) {
        $nota = calificativGrade($this, $case->value);

        expect($nota->fresh()->calificativ)->toBe($case->value);

        $nota->forceDelete();
    }
});

it('aprobarea unei corecții nu poate strecura un simbol străin în notă', function () {
    $nota = calificativGrade($this, 'B');

    // Cererea se fabrică prin QUERY BUILDER, deci ocolește și garda cererii — scenariul „rândul
    // rău a intrat pe altă cale" (import, migrare, bug). Al doilea strat trebuie să țină singur.
    $cerereId = DB::table('grade_corrections')->insertGetId([
        'grade_id' => $nota->id,
        'requested_by_user_id' => $this->teacherUser->id,
        'old_calificativ' => 'B',
        'new_calificativ' => 'test Calif',
        'reason' => 'propunere cu simbol inventat',
        'status' => CorrectionStatus::Pending->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cerere = GradeCorrection::query()->findOrFail($cerereId);

    expect(fn () => $cerere->approve($this->teacherUser->id))->toThrow(ValidationException::class);

    // Nota a rămas neatinsă: garda a oprit scrierea, nu a lăsat-o pe jumătate.
    expect($nota->fresh()->calificativ)->toBe('B');
});

it('un rând istoric cu simbol greșit rămâne editabil pe CELELALTE câmpuri', function () {
    // Importul legacy scrie prin query builder, deci ocolește garda — așa au ajuns în baza reală
    // două calificative greșite („R", „7"). Garda nu trebuie să le transforme în note blocate.
    $id = DB::table('grades')->insertGetId([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'term_id' => $this->term->id,
        'graded_on' => Carbon::today()->subDays(5)->toDateString(),
        'type' => 1,
        'evaluation_type' => 'curenta',
        'calificativ' => 'R',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $nota = Grade::query()->findOrFail($id);
    $nota->update(['annulment_reason' => 'corectat în registrul de hârtie']);

    expect($nota->fresh()->annulment_reason)->toBe('corectat în registrul de hârtie')
        ->and($nota->fresh()->calificativ)->toBe('R');

    // Dar dacă cineva ATINGE simbolul, regula se aplică din nou.
    expect(fn () => $nota->update(['calificativ' => 'alt inventat']))->toThrow(ValidationException::class);
});

it('cererea de corecție respinge simbolul inventat și acceptă unul din scală', function () {
    $nota = calificativGrade($this, 'B');

    actingAs($this->teacherUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->callAction('requestGradeCorrection', [
            'new_calificativ' => 'test Calif',
            'reason' => 'simbol inventat',
        ], arguments: ['id' => $nota->id])
        ->assertHasActionErrors(['new_calificativ']);

    expect(GradeCorrection::query()->count())->toBe(0);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->callAction('requestGradeCorrection', [
            'new_calificativ' => 'FB',
            'reason' => 'lucrarea susține un calificativ mai mare',
        ], arguments: ['id' => $nota->id]);

    expect(GradeCorrection::query()->where('grade_id', $nota->id)->value('new_calificativ'))->toBe('FB');
});

it('propunerea numerică respectă scala notei: întreagă și în 1–10', function () {
    $numerica = Subject::factory()->create(['grading_type' => GradingType::Numeric]);

    $nota = Grade::query()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $numerica->id,
        'teacher_id' => $this->teacher->id,
        'term_id' => $this->term->id,
        'graded_on' => Carbon::today()->subDays(3)->toDateString(),
        'type' => 1,
        'evaluation_type' => 'curenta',
        'value' => 7,
    ]);

    $propune = fn (float $valoare) => GradeCorrection::query()->create([
        'grade_id' => $nota->id,
        'requested_by_user_id' => $this->teacherUser->id,
        'old_value' => 7,
        'new_value' => $valoare,
        'reason' => 'propunere de test',
    ]);

    // Zecimala aparține mediilor, nu notei; iar 99 e în afara scalei 1–10. Regula din formular
    // ținea doar cât timp câmpul era vizibil — garda de model o face necondiționată.
    expect(fn () => $propune(6.5))->toThrow(ValidationException::class)
        ->and(fn () => $propune(99))->toThrow(ValidationException::class)
        ->and(fn () => $propune(0))->toThrow(ValidationException::class);

    expect(GradeCorrection::query()->count())->toBe(0);

    $propune(6);

    expect((float) GradeCorrection::query()->value('new_value'))->toBe(6.0);
});

it('simbolul tastat în borderou se iartă la majuscule, dar nu se inventează', function () {
    expect(Calificativ::normalize('fb'))->toBe(Calificativ::FoarteBine)
        ->and(Calificativ::normalize(' SP '))->toBe(Calificativ::CuSprijin)
        ->and(Calificativ::normalize('S'))->toBe(Calificativ::Suficient)
        ->and(Calificativ::normalize('test Calif'))->toBeNull()
        ->and(Calificativ::normalize(''))->toBeNull();
});
