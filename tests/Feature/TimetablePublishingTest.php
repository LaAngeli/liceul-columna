<?php

/**
 * Inversarea lanțului orarelor (2026-08-01): orarul STRUCTURAT e sursa, tabelul PUBLICAT pe site e
 * derivatul. Se verifică aici că derivarea chiar se întâmplă la fiecare schimbare (altfel cele două
 * ar diverge tăcut, exact starea de dinainte) și că nu ia cu ea ce nu-i aparține — rândurile de
 * program ale zilei, care nu au corespondent în sloturi.
 *
 * Parsarea celulei la import stă în {@see ImportLessonsTest}, invarianții disciplinei în
 * {@see TimetableIntegrityTest}.
 */

use App\Actions\PublishClassTimetable;
use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;

/** Tabelul publicat al clasei, cu un rând-lecție și unul de program. */
function publishingSchedule(SchoolClass $class): Schedule
{
    return Schedule::query()->create([
        'type' => ScheduleType::Lessons->value,
        'label' => 'Clasa '.$class->name,
        'school_class_id' => $class->id,
        'is_public' => true,
        'headers' => ['', 'Luni', 'Marți'],
        'rows' => [
            ['Lecția 1 08.15 – 09.00', 'text vechi', ''],
            ['13.05 – 14.05', 'Plimbări, jocuri', 'Plimbări, jocuri'],
        ],
    ]);
}

function publishingClass(): SchoolClass
{
    $year = AcademicYear::factory()->create();

    return SchoolClass::factory()->for($year)->create(['grade_level' => 8]);
}

it('un slot nou recompune celula din tabelul publicat, fără să atingă rândurile de program', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);
    $subject = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
    $teacher = Teacher::factory()->create(['last_name' => 'Damian', 'first_name' => 'Iurie']);

    Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'teacher_name' => null,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
        'room' => '20',
    ]);

    $rows = $schedule->fresh()->rows;

    // Celula lecției vine acum din slot — disciplina, profesorul și sala, în forma pe care
    // WeeklySchedule o citește înapoi pentru cabinet.
    expect($rows[0][1])->toBe('Matematică, Damian I. (s. 20)')
        // Ziua fără slot rămâne goală, nu păstrează ce era înainte.
        ->and($rows[0][2])->toBe('')
        // Rândul de program al zilei NU e conținut predat: sloturile nu-l descriu, deci nu-l ating.
        ->and($rows[1])->toBe(['13.05 – 14.05', 'Plimbări, jocuri', 'Plimbări, jocuri'])
        // Eticheta rândului poartă intervalul real și rămâne a administratorului.
        ->and($rows[0][0])->toBe('Lecția 1 08.15 – 09.00');
});

it('ștergerea slotului golește celula, nu lasă în urmă textul publicat', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    $lesson = Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => $subject->id,
        'teacher_id' => null,
        'teacher_name' => null,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
        'room' => null,
    ]);

    expect($schedule->fresh()->rows[0][1])->not->toBe('');

    $lesson->delete();

    expect($schedule->fresh()->rows[0][1])->toBe('');
});

it('două grupe în același interval ajung amândouă în celulă', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);
    $english = Subject::factory()->create(['name' => 'Limba engleză', 'min_grade' => 5, 'max_grade' => 12]);
    $it = Subject::factory()->create(['name' => 'Informatică', 'min_grade' => 5, 'max_grade' => 12]);

    foreach ([['1', $english], ['2', $it]] as [$group, $subject]) {
        Lesson::factory()->create([
            'school_class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'subject_id' => $subject->id,
            'teacher_id' => null,
            'teacher_name' => null,
            'student_group' => $group,
            'day_of_week' => Weekday::Monday,
            'lesson_number' => 1,
            'room' => null,
        ]);
    }

    expect($schedule->fresh()->rows[0][1])->toBe('Limba engleză , gr.1 Informatică , gr.2');
});

it('slotul fără disciplină din nomenclator își poartă eticheta pe site', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);

    Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => null,
        'title' => 'Managementul clasei',
        'teacher_id' => null,
        'teacher_name' => 'Rotaru E.',
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
        'room' => null,
    ]);

    expect($schedule->fresh()->rows[0][1])->toBe('Managementul clasei, Rotaru E.');
});

it('un rând-lecție lipsă se adaugă, cu intervalul din orarul sunetelor', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);

    Schedule::query()->create([
        'type' => ScheduleType::Bells->value,
        'label' => 'Clasele gimnaziale și liceale (V – XII)',
        'is_public' => true,
        'headers' => ['Activitate', 'Interval de timp'],
        'rows' => [['Lecția 1', '08.15 - 09.00'], ['Lecția 3', '10.10 - 10.55']],
    ]);

    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => $subject->id,
        'teacher_id' => null,
        'teacher_name' => null,
        'day_of_week' => Weekday::Tuesday,
        'lesson_number' => 3,
        'room' => null,
    ]);

    $rows = $schedule->fresh()->rows;

    // Orele nu se dublează în orarul lecțiilor: sursa lor oficială e orarul sunetelor.
    expect($rows)->toHaveCount(3)
        ->and($rows[2][0])->toBe('Lecția 3 10.10 – 10.55')
        ->and($rows[2][2])->not->toBe('');
});

it('publicarea suspendată lasă tabelul neatins — cine suspendă, publică', function () {
    $class = publishingClass();
    $schedule = publishingSchedule($class);
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    PublishClassTimetable::withoutAutoPublish(fn () => Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => $subject->id,
        'teacher_id' => null,
        'teacher_name' => null,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
        'room' => null,
    ]));

    expect($schedule->fresh()->rows[0][1])->toBe('text vechi');

    app(PublishClassTimetable::class)->handle($class);

    expect($schedule->fresh()->rows[0][1])->not->toBe('text vechi');
});

it('în „Orare", rândurile orarului de lecții sunt blocate; celelalte tipuri rămân editabile', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::AdministratorOperational->value);

    $class = publishingClass();
    $generated = publishingSchedule($class);

    $manual = Schedule::query()->create([
        'type' => ScheduleType::Bells->value,
        'label' => 'Orarul sunetelor',
        'is_public' => true,
        'headers' => ['Activitate', 'Interval de timp'],
        'rows' => [['Lecția 1', '08.15 - 09.00']],
    ]);

    $this->actingAs($admin);

    // Generat: câmpurile derivate sunt blocate, iar avertismentul care explică de ce e afișat.
    Livewire::test(EditSchedule::class, ['record' => $generated->getRouteKey()])
        ->assertFormFieldDisabled('rows')
        ->assertFormFieldDisabled('headers')
        ->assertSee(__('panel.forms.schedule.generated_title'));

    // Scris de om: nimic blocat.
    Livewire::test(EditSchedule::class, ['record' => $manual->getRouteKey()])
        ->assertFormFieldEnabled('rows')
        ->assertFormFieldEnabled('headers')
        ->assertDontSee(__('panel.forms.schedule.generated_title'));
});
