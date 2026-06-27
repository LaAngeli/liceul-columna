<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

it('retenția: dry-run nu șterge, --force șterge dosarul expirat cu cascadă, păstrează cel activ', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $term = Term::factory()->for($year)->create();
    $subject = Subject::factory()->create();

    // Elev EXPIRAT: plecat acum 13 ani (> retenția de 12), nicio înrolare activă.
    $expired = Student::factory()->create();
    Enrollment::factory()->for($expired)->for($class)->for($year)->create(['left_on' => now()->subYears(13)]);
    Grade::factory()->create([
        'student_id' => $expired->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
    ]);

    // Elev ACTIV: înrolare fără left_on → NU se atinge.
    $active = Student::factory()->create();
    Enrollment::factory()->for($active)->for($class)->for($year)->create(['left_on' => null]);

    // Dry-run (implicit): nu șterge nimic.
    $this->artisan('app:purge-expired-students')->assertSuccessful();
    expect(Student::withTrashed()->find($expired->id))->not->toBeNull();

    // --force: șterge definitiv doar dosarul expirat (cu cascada notelor); activul rămâne.
    $this->artisan('app:purge-expired-students', ['--force' => true])->assertSuccessful();

    expect(Student::withTrashed()->find($expired->id))->toBeNull()
        ->and(Grade::where('student_id', $expired->id)->exists())->toBeFalse()
        ->and(Student::find($active->id))->not->toBeNull();
});
