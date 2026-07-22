<?php

use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\WeeklySchedule;

/**
 * Normalizatorul orarului săptămânal ({@see WeeklySchedule}) — parserul de AFIȘARE al celulelor
 * de text liber din orarele publicate: segmentare pe discipline, extracție profesor/grupă/sală,
 * activități, banda uniformă, fallback structurat.
 */
function parser(): WeeklySchedule
{
    return app(WeeklySchedule::class);
}

it('segmentează celula cu două grupe paralele: disciplină + grupă + profesor per segment', function () {
    Subject::factory()->create(['name' => 'Limba străină 1 (engleza)']);

    $cell = parser()->parseCell('Limba engleză , gr.1, Popa N. Limba engleză , gr.2, Buga A.');

    expect($cell['segments'])->toHaveCount(2)
        ->and($cell['segments'][0])->toBe(['subject' => 'Limba engleză', 'teacher' => 'Popa N.', 'group' => 'gr. 1'])
        ->and($cell['segments'][1])->toBe(['subject' => 'Limba engleză', 'teacher' => 'Buga A.', 'group' => 'gr. 2']);
});

it('extrage profesorul terminal și păstrează disciplina curată', function () {
    Subject::factory()->create(['name' => 'Educație muzicală']);

    // Diacriticele legacy cu sedilă (Educaţie) se normalizează la forma standard.
    $cell = parser()->parseCell('Educaţie muzicală , Ungureanu V.');

    expect($cell['segments'])->toHaveCount(1)
        ->and($cell['segments'][0]['subject'])->toBe('Educație muzicală')
        ->and($cell['segments'][0]['teacher'])->toBe('Ungureanu V.');
});

it('nu confundă activitățile cu profesori sau discipline: celula rămâne întreagă', function () {
    // „jocuri" nu e un „Nume V." — nimic nu se pierde, nimic nu se inventează.
    $cell = parser()->parseCell('Plimbări, jocuri');

    expect($cell['segments'])->toHaveCount(1)
        ->and($cell['segments'][0])->toBe(['subject' => 'Plimbări, jocuri', 'teacher' => null, 'group' => null]);
});

it('extrage sala din „(s. NN)"', function () {
    Subject::factory()->create(['name' => 'Matematică']);

    $cell = parser()->parseCell('Matematică Damian Iu. (s. 20)');

    expect($cell['room'])->toBe('20')
        ->and($cell['segments'][0]['subject'])->toBe('Matematică')
        ->and($cell['segments'][0]['teacher'])->toBe('Damian Iu.');
});

it('numele compuse cu cratimă („Bujor-Cobili C.") sunt recunoscute ca profesor', function () {
    Subject::factory()->create(['name' => 'Istorie']);

    $cell = parser()->parseCell('Istorie Bujor-Cobili C.');

    expect($cell['segments'][0]['teacher'])->toBe('Bujor-Cobili C.')
        ->and($cell['segments'][0]['subject'])->toBe('Istorie');
});

it('profesorul scris doar cu numele + punct („, Iurco.") e recunoscut după virgulă', function () {
    Subject::factory()->create(['name' => 'Informatică']);

    $cell = parser()->parseCell('Informatică , gr.1, Iurco.');

    expect($cell['segments'][0])->toBe(['subject' => 'Informatică', 'teacher' => 'Iurco.', 'group' => 'gr. 1']);
});

it('normalizează orarul publicat: sloturi cu număr+interval, activități, bandă uniformă', function () {
    Subject::factory()->create(['name' => 'Matematică']);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa IV N',
        'school_class_id' => $class->id,
        'is_public' => true,
        'headers' => ['', 'Luni', 'Marți'],
        'rows' => [
            ['Lecția 1 08.00 – 08.45', 'Matematică', 'Matematică'],
            ['12.15 – 13.00', 'Plimbări, jocuri', 'Plimbări, jocuri'],
            ['14.15 – 15.00', 'PTA 1', 'Club de șah'],
        ],
    ]);

    $weekly = parser()->forClass($class->fresh());

    expect($weekly['source'])->toBe('published')
        ->and($weekly['days'])->toHaveCount(2)
        ->and($weekly['slots'][0]['number'])->toBe(1)
        ->and($weekly['slots'][0]['time'])->toBe('08.00–08.45')
        ->and($weekly['slots'][0]['kind'])->toBe('lesson')
        ->and($weekly['slots'][0]['uniform'])->toBeNull()
        // Activitate IDENTICĂ pe toate zilele → bandă uniformă (colspan), celulele golite.
        ->and($weekly['slots'][1]['kind'])->toBe('activity')
        ->and($weekly['slots'][1]['uniform']['raw'])->toBe('Plimbări, jocuri')
        ->and($weekly['slots'][1]['cells'])->toBe([])
        // Activitate DIFERITĂ pe zile → rămâne per-celulă.
        ->and($weekly['slots'][2]['uniform'])->toBeNull()
        ->and($weekly['slots'][2]['cells'][1]['raw'])->toBe('PTA 1')
        ->and($weekly['slots'][2]['cells'][2]['raw'])->toBe('Club de șah');
});

it('completează sala lecției din orarul structurat când textul publicat nu o are', function () {
    $subject = Subject::factory()->create(['name' => 'Matematică']);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa V A',
        'school_class_id' => $class->id,
        'is_public' => true,
        'headers' => ['', 'Luni'],
        'rows' => [['Lecția 1 08.00 – 08.45', 'Matematică']],
    ]);
    Lesson::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
        'room' => '20',
    ]);

    $weekly = parser()->forClass($class->fresh());

    expect($weekly['slots'][0]['cells'][1]['room'])->toBe('20');
});

it('fără orar publicat cade pe structurat; fără niciunul → null', function () {
    $subject = Subject::factory()->create(['name' => 'Matematică']);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();

    expect(parser()->forClass($class))->toBeNull();

    Lesson::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Tuesday,
        'lesson_number' => 3,
        'room' => '7',
    ]);

    $weekly = parser()->forClass($class->fresh());

    expect($weekly['source'])->toBe('structured')
        ->and($weekly['slots'][0]['number'])->toBe(3)
        ->and($weekly['slots'][0]['cells'][2]['segments'][0]['subject'])->toBe('Matematică')
        ->and($weekly['slots'][0]['cells'][2]['room'])->toBe('7');
});
