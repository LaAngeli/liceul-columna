<?php

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarAggregator;
use App\Calendar\CalendarScope;
use App\Enums\CalendarEventType;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Modul Calendar V2b: ManualEventProjector duce evenimentele manuale în feed — în cabinet filtrate pe
 * audiența copilului (global/treaptă/clasă), iar la staff toate (calendarul instituțional).
 */
function enrolledStudent(int $grade, string $section): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => $grade, 'section' => $section]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    return [$student, $class];
}

function familyScope(Student $student): CalendarScope
{
    return new CalendarScope(User::factory()->create(), collect([$student]));
}

function collectManual(CalendarScope $scope): Collection
{
    return collect(app(CalendarAggregator::class)->collect(
        $scope,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    ));
}

it('evenimentul global apare în cabinetul copilului', function () {
    [$student] = enrolledStudent(9, 'A');

    CalendarEvent::factory()->create(['title' => 'Ziua porților deschise', 'starts_on' => '2026-06-15']);

    expect(collectManual(familyScope($student))->where('source', 'calendar_event')->pluck('title'))
        ->toContain('Ziua porților deschise');
});

it('evenimentul de clasă apare doar la copilul din acea clasă', function () {
    [$inClass, $class] = enrolledStudent(9, 'A');
    [$otherClass] = enrolledStudent(9, 'B');

    CalendarEvent::factory()->forClass($class->id)->create([
        'type' => CalendarEventType::Meeting,
        'title' => 'Ședință clasa A',
        'starts_on' => '2026-06-12',
    ]);

    expect(collectManual(familyScope($inClass))->pluck('title'))->toContain('Ședință clasa A')
        ->and(collectManual(familyScope($otherClass))->pluck('title'))->not->toContain('Ședință clasa A');
});

it('evenimentul de treaptă apare la toate clasele treptei, dar nu la altă treaptă', function () {
    [$grade9] = enrolledStudent(9, 'A');
    [$grade10] = enrolledStudent(10, 'A');

    CalendarEvent::factory()->forGrade(9)->create(['title' => 'Olimpiada treaptă 9', 'starts_on' => '2026-06-20']);

    expect(collectManual(familyScope($grade9))->pluck('title'))->toContain('Olimpiada treaptă 9')
        ->and(collectManual(familyScope($grade10))->pluck('title'))->not->toContain('Olimpiada treaptă 9');
});

it('staff vede toate evenimentele manuale (calendar instituțional)', function () {
    CalendarEvent::factory()->forClass(SchoolClass::factory()->create()->id)->create([
        'title' => 'Eveniment de clasă oarecare',
        'starts_on' => '2026-06-10',
    ]);

    $staff = User::factory()->create();
    $scope = app(CalendarAccess::class)->staffScope($staff);

    expect(collectManual($scope)->pluck('title'))->toContain('Eveniment de clasă oarecare');
});

it('evenimentul pe mai multe zile apare pe fiecare zi din interval', function () {
    [$student] = enrolledStudent(9, 'A');

    CalendarEvent::factory()->create([
        'title' => 'Săptămâna verde',
        'starts_on' => '2026-06-08',
        'ends_on' => '2026-06-10',
    ]);

    $dates = collectManual(familyScope($student))
        ->where('title', 'Săptămâna verde')
        ->pluck('date');

    expect($dates)->toContain('2026-06-08', '2026-06-09', '2026-06-10');
});
