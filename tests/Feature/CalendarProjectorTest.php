<?php

use App\Calendar\CalendarAggregator;
use App\Calendar\CalendarScope;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Modul Calendar C3: proiectoarele agregă sursele reale într-un singur feed pentru un elev și un
 * interval. Verifică și scope-ul pe clasă (tema altei clase NU apare) + structura globală (vacanță).
 */
function calendarScopeFor(Student $student): CalendarScope
{
    return new CalendarScope(User::factory()->create(), collect([$student]));
}

function collectCalendar(CalendarScope $scope): Collection
{
    $items = app(CalendarAggregator::class)->collect(
        $scope,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    return collect($items);
}

it('agregă teme, absențe și structura pentru elevul din scope', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 9, 'section' => 'A']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    HomeworkAssignment::factory()->create([
        'grade_level' => 9,
        'section' => 'A',
        'subject_name' => 'Matematică',
        'assigned_on' => '2026-06-10',
    ]);

    Absence::factory()->for($student)->create([
        'occurred_on' => '2026-06-12',
        'is_motivated' => false,
    ]);

    Holiday::create(['name' => 'Vacanța de vară', 'starts_on' => '2026-06-15']);

    $items = collectCalendar(calendarScopeFor($student));

    expect($items->pluck('source'))->toContain('homework', 'absence', 'holiday')
        ->and($items->where('source', 'homework')->pluck('title'))->toContain('Matematică');
});

it('nu include tema unei alte clase', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 9, 'section' => 'A']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    HomeworkAssignment::factory()->create([
        'grade_level' => 9, 'section' => 'A', 'subject_name' => 'Matematică', 'assigned_on' => '2026-06-10',
    ]);
    HomeworkAssignment::factory()->create([
        'grade_level' => 10, 'section' => 'B', 'subject_name' => 'Biologie', 'assigned_on' => '2026-06-11',
    ]);

    $titles = collectCalendar(calendarScopeFor($student))->where('source', 'homework')->pluck('title');

    expect($titles)->toContain('Matematică')
        ->and($titles)->not->toContain('Biologie');
});

it('proiectează limitele de semestru ca evenimente de structură', function () {
    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['starts_on' => '2026-06-02', 'ends_on' => '2026-06-28']);

    $student = Student::factory()->create();

    $structure = collectCalendar(calendarScopeFor($student))->where('source', 'term');

    expect($structure)->toHaveCount(2)
        ->and($structure->pluck('date'))->toContain('2026-06-02', '2026-06-28');
});

it('evenimentele PII poartă deep-link către secțiunea din cabinet + titlu localizat', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 9, 'section' => 'A']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    Absence::factory()->for($student)->create(['occurred_on' => '2026-06-12', 'is_motivated' => false]);

    $absence = collectCalendar(calendarScopeFor($student))->firstWhere('source', 'absence');

    expect($absence->deepLink)->toBe("/cabinet/elev/{$student->id}#absences")
        ->and($absence->title)->toBe('Absență');
});
