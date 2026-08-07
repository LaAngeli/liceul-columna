<?php

use App\Models\Absence;
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
use Illuminate\Support\Carbon;

/**
 * ELEVUL DEMO STĂ ÎN CLASA ÎNVĂȚĂTORULUI SĂU (cerința beneficiarului, 07.08.2026): contul `elev@`
 * trebuie să fie în clasa predată ȘI dirijată de fișa contului `director@`, ca pe un singur cont cu
 * trei roluri să se vadă același copil din trei unghiuri.
 *
 * Ce se verifică aici e partea care putea eșua tăcut: nu doar înscrierea, ci și CONSEMNĂRILE.
 * Mutat singur, elevul ar fi apărut în catalogul noului învățător cu totul gol, iar notele lui ar fi
 * rămas semnate de un profesor care nu predă în clasa aceea.
 */
beforeEach(function (): void {
    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year)->create([
        'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(3),
        'ends_on' => Carbon::today()->addMonth(),
    ]);

    $this->subject = Subject::factory()->create(['name' => 'Matematică']);

    // Învățătorul de plecare și cel de destinație — al doilea e fișa contului `director@`.
    $this->oldTeacher = Teacher::factory()->create();
    $directorUser = User::factory()->create(['email' => 'director@columna.test']);
    $this->newTeacher = Teacher::factory()->create(['user_id' => $directorUser->id]);

    $this->from = SchoolClass::factory()->for($this->year)->create([
        'name' => '[DEMO] 1A', 'grade_level' => 1, 'section' => 'A',
        'homeroom_teacher_id' => $this->oldTeacher->id,
    ]);
    $this->to = SchoolClass::factory()->for($this->year)->create([
        'name' => '[DEMO] 1B', 'grade_level' => 1, 'section' => 'B',
        'homeroom_teacher_id' => $this->newTeacher->id,
    ]);

    foreach ([[$this->from, $this->oldTeacher], [$this->to, $this->newTeacher]] as [$class, $teacher]) {
        TeachingAssignment::factory()->create([
            'school_class_id' => $class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    $elevUser = User::factory()->create(['email' => 'elev@columna.test']);
    $this->student = Student::factory()->create(['user_id' => $elevUser->id]);

    $this->enrollment = Enrollment::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->from->id,
        'academic_year_id' => $this->year->id,
        'left_on' => null,
    ]);
});

it('mută elevul contului demo în clasa dirijată de contul director, cu tot ce are consemnat', function (): void {
    $grade = Grade::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->from->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->oldTeacher->id,
        'term_id' => $this->term->id,
    ]);

    $absence = Absence::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->from->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->oldTeacher->id,
        'term_id' => $this->term->id,
    ]);

    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect($this->enrollment->fresh()->school_class_id)->toBe($this->to->id)
        // Nota îl urmează ȘI trece pe învățătorul clasei noi — altfel catalogul ar afirma că a
        // pus-o cineva care nu predă acolo.
        ->and($grade->fresh()->school_class_id)->toBe($this->to->id)
        ->and($grade->fresh()->teacher_id)->toBe($this->newTeacher->id)
        ->and($absence->fresh()->school_class_id)->toBe($this->to->id)
        ->and($absence->fresh()->teacher_id)->toBe($this->newTeacher->id);
});

it('nu atinge colegii rămași în clasa veche', function (): void {
    $other = Student::factory()->create();
    $otherEnrollment = Enrollment::factory()->create([
        'student_id' => $other->id,
        'school_class_id' => $this->from->id,
        'academic_year_id' => $this->year->id,
        'left_on' => null,
    ]);
    $otherGrade = Grade::factory()->create([
        'student_id' => $other->id,
        'school_class_id' => $this->from->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->oldTeacher->id,
        'term_id' => $this->term->id,
    ]);

    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect($otherEnrollment->fresh()->school_class_id)->toBe($this->from->id)
        ->and($otherGrade->fresh()->school_class_id)->toBe($this->from->id)
        ->and($otherGrade->fresh()->teacher_id)->toBe($this->oldTeacher->id);
});

it('este idempotentă — a doua rulare nu mai mișcă nimic', function (): void {
    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();
    $afterFirst = $this->enrollment->fresh()->updated_at;

    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect($this->enrollment->fresh()->school_class_id)->toBe($this->to->id)
        ->and($this->enrollment->fresh()->updated_at->eq($afterFirst))->toBeTrue();
});

it('lasă înscrierea neatinsă dacă dirigintele-țintă nu are clasă pe treapta elevului', function (): void {
    // Contul director rămâne diriginte doar la o clasă de altă treaptă.
    $this->to->update(['grade_level' => 5]);

    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect($this->enrollment->fresh()->school_class_id)->toBe($this->from->id);
});
