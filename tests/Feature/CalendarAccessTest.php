<?php

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarAggregator;
use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * Modul Calendar C2: garda de scoping e sursa unică „cine vede ce". Acoperă scenariile pe care le-a
 * semnalat critica: familie ≠ familie (cross-family), tutore revocat, elev transferat (left_on).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function calendarAccess(): CalendarAccess
{
    return new CalendarAccess;
}

it('tutorele își vede copilul, dar nu copilul altei familii', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $child = Student::factory()->create();
    $otherChild = Student::factory()->create();

    $parent->students()->attach($child->id);

    expect(calendarAccess()->canViewStudentCalendar($parent, $child))->toBeTrue()
        ->and(calendarAccess()->canViewStudentCalendar($parent, $otherChild))->toBeFalse();
});

it('elevul își vede propriul calendar', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);

    $student = Student::factory()->create(['user_id' => $user->id]);

    expect(calendarAccess()->canViewStudentCalendar($user, $student))->toBeTrue();
});

it('un tutore revocat nu mai vede copilul', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $child = Student::factory()->create();
    $parent->students()->attach($child->id);

    expect(calendarAccess()->canViewStudentCalendar($parent, $child))->toBeTrue();

    $parent->students()->detach($child->id);

    expect(calendarAccess()->canViewStudentCalendar($parent->fresh(), $child))->toBeFalse();
});

it('administrația academică vede calendarul oricărui elev', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $student = Student::factory()->create();

    expect(calendarAccess()->canViewStudentCalendar($director, $student))->toBeTrue();
});

it('elevul transferat nu mai e vizibil în afara perioadei de înrolare', function () {
    $student = Student::factory()->create();

    Enrollment::factory()
        ->for($student)
        ->for(SchoolClass::factory())
        ->for(AcademicYear::factory())
        ->create(['enrolled_on' => '2026-01-01', 'left_on' => '2026-03-01']);

    $access = calendarAccess();

    expect($access->wasEnrolledOn($student, Carbon::parse('2026-02-10')))->toBeTrue()
        ->and($access->wasEnrolledOn($student, Carbon::parse('2026-06-10')))->toBeFalse()
        ->and($access->wasEnrolledOn($student, Carbon::parse('2025-12-10')))->toBeFalse();
});

it('agregatorul deduplică evenimentele identice și le sortează cronologic', function () {
    $projector = new class implements CalendarProjector
    {
        public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
        {
            return [
                new CalendarItem('a', 'src', CalendarCategory::Event, 'Ședință', '2026-06-12'),
                new CalendarItem('a-dup', 'src', CalendarCategory::Event, 'Ședință', '2026-06-12'),
                new CalendarItem('b', 'src', CalendarCategory::Homework, 'Temă', '2026-06-05'),
            ];
        }
    };

    $aggregator = new CalendarAggregator([$projector]);

    $scope = new CalendarScope(User::factory()->create(), collect());

    $items = $aggregator->collect($scope, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($items)->toHaveCount(2)
        ->and($items[0]->date)->toBe('2026-06-05')
        ->and($items[1]->date)->toBe('2026-06-12');
});
