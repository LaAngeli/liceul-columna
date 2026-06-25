<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;

it('leagă o notă de elev, disciplină, clasă, semestru și profesor', function () {
    $grade = Grade::factory()->create();

    expect($grade->student)->toBeInstanceOf(App\Models\Student::class)
        ->and($grade->subject)->toBeInstanceOf(App\Models\Subject::class)
        ->and($grade->schoolClass)->toBeInstanceOf(SchoolClass::class)
        ->and($grade->term)->toBeInstanceOf(App\Models\Term::class)
        ->and($grade->teacher)->toBeInstanceOf(App\Models\Teacher::class);
});

it('înmatriculează un elev într-o clasă pentru un an școlar', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();

    $enrollment = Enrollment::factory()
        ->for($student)
        ->for($class)
        ->for($year)
        ->create();

    expect($enrollment->schoolClass->academic_year_id)->toBe($year->id)
        ->and($student->enrollments)->toHaveCount(1)
        ->and($year->schoolClasses->pluck('id'))->toContain($class->id);
});

it('împiedică doi elevi în aceeași clasă pe același an (regula de unicitate)', function () {
    $student = Student::factory()->create();
    $year = AcademicYear::factory()->create();

    Enrollment::factory()->for($student)->for($year)->create();

    expect(fn () => Enrollment::factory()->for($student)->for($year)->create())
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('păstrează istoricul modificărilor unei note (audit)', function () {
    config(['audit.console' => true]);

    $grade = Grade::factory()->create(['value' => 7]);
    $grade->update(['value' => 9]);

    $audit = $grade->audits()->latest('id')->first();

    expect($grade->audits)->toHaveCount(2) // created + updated
        ->and($audit->event)->toBe('updated')
        ->and((float) $audit->old_values['value'])->toBe(7.0)
        ->and((float) $audit->new_values['value'])->toBe(9.0);
});

it('aplică soft delete pe note', function () {
    $grade = Grade::factory()->create();
    $grade->delete();

    expect(Grade::count())->toBe(0)
        ->and(Grade::withTrashed()->count())->toBe(1);
});
