<?php

/**
 * Importul orarului STRUCTURAT (Lesson) din orarele PUBLICATE ale claselor — best-effort:
 * doar rândurile „Lecția N", doar celulele care încep cert cu o disciplină din nomenclator;
 * grupele mixte (discipline diferite în același slot) se sar. Activează riscul de amânare.
 *
 * Aici se verifică PARSAREA celulei. Rezolvarea disciplinei pe treapta clasei, aliasurile de
 * vocabular și invarianții slotului stau în {@see TimetableIntegrityTest}.
 */

use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;

function lessonsSchedule(SchoolClass $class): Schedule
{
    return Schedule::query()->create([
        'type' => ScheduleType::Lessons->value,
        'label' => 'Clasa '.trim($class->name.' '.($class->section ?? '')),
        'school_class_id' => $class->id,
        'is_public' => true,
        'headers' => ['', 'Luni', 'Marți'],
        'rows' => [
            // Mono-disciplină cu profesor + sală; diacritice LEGACY cu sedilă pe marți.
            ['Lecția 1 08.15 – 09.00', 'Matematică Damian Iu. (s. 20)', "Educa\u{0163}ie fizic\u{0103} Ciobanu A."],
            // Grupe cu ACEEAȘI disciplină (importabilă) / grupe MIXTE (se sare).
            ['Lecția 2 09.10 – 09.55', 'Limba engleză , gr.1, Pascaru M. (s. 14) Limba engleză , gr.2, Cociug S. (s. 21)', 'Informatică , gr.1, Iurco O. (s. 28) Limba engleză , gr.2 (s. 21)'],
            // Rând care NU e lecție (program prelungit) — se sare natural.
            ['13.05 – 14.05', 'Plimbări, jocuri', 'Plimbări, jocuri'],
        ],
    ]);
}

it('importă fiecare celulă integral — grupele ca sloturi distincte, restul ca text; e idempotentă', function () {
    $year = AcademicYear::factory()->create();
    // Treaptă FIXĂ: importul rezolvă disciplina pe intervalul de trepte al fișei, iar
    // SubjectFactory acoperă 5-12. Cu treapta aleatorie a factory-ului de clasă (1-12), o clasă
    // primară n-ar găsi nicio disciplină și testul ar pica ~1 rulare din 3, fără vină reală.
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8]);

    $math = Subject::factory()->create(['name' => 'Matematică']);
    $pe = Subject::factory()->create(['name' => 'Educație fizică']);
    $english = Subject::factory()->create(['name' => 'Limba engleză']);
    Subject::factory()->create(['name' => 'Informatică']);

    lessonsSchedule($class);

    $this->artisan('app:import-lessons')->assertSuccessful();

    $slots = Lesson::query()->where('school_class_id', $class->id)->get();

    // De când orarul publicat se GENEREAZĂ din sloturi, importul nu mai are voie să sară celule:
    // ce nu intră aici dispare de pe site. Rezultă 6 sloturi — două lecții simple (luni/marți L1)
    // plus câte două pe grupe, în ambele celule de la L2, inclusiv cea cu discipline diferite.
    expect($slots)->toHaveCount(6)
        ->and($slots->firstWhere('lesson_number', 1)?->subject_id)->toBe($math->id)
        ->and($slots->firstWhere('lesson_number', 1)?->room)->toBe('20')
        ->and($slots->where('day_of_week', Weekday::Tuesday)->first()?->subject_id)->toBe($pe->id);

    // Aceeași disciplină pe două grupe: două sloturi distincte, cu grupa ca discriminator.
    $monday = $slots->where('lesson_number', 2)->where('day_of_week', Weekday::Monday);

    expect($monday)->toHaveCount(2)
        ->and($monday->pluck('student_group')->sort()->values()->all())->toBe(['1', '2'])
        ->and($monday->pluck('subject_id')->unique()->all())->toBe([$english->id])
        // Numele scris în orar se păstrează, chiar și potrivit cu o fișă: „Pascaru M." nu e
        // același lucru cu ce s-ar deriva dintr-o fișă omonimă.
        ->and($monday->firstWhere('student_group', '1')?->teacher_name)->toBe('Pascaru M.');

    // Grupe cu discipline DIFERITE: nu se mai sar — grupa le separă în sloturi proprii.
    $tuesday = $slots->where('lesson_number', 2)->where('day_of_week', Weekday::Tuesday);

    expect($tuesday)->toHaveCount(2)
        ->and($tuesday->pluck('student_group')->sort()->values()->all())->toBe(['1', '2']);

    // Idempotență: re-rularea cu --force nu duplică sloturile.
    $this->artisan('app:import-lessons --force')->assertSuccessful();
    expect(Lesson::query()->where('school_class_id', $class->id)->count())->toBe(6);
});

it('clasa cu orar structurat introdus deja NU e atinsă fără --force', function () {
    $year = AcademicYear::factory()->create();
    // Treaptă FIXĂ: importul rezolvă disciplina pe intervalul de trepte al fișei, iar
    // SubjectFactory acoperă 5-12. Cu treapta aleatorie a factory-ului de clasă (1-12), o clasă
    // primară n-ar găsi nicio disciplină și testul ar pica ~1 rulare din 3, fără vină reală.
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8]);
    Subject::factory()->create(['name' => 'Matematică']);

    // Slot introdus manual (alt subiect, altă zi) — trebuie să supraviețuiască importului simplu.
    $manual = Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $year->id,
        'day_of_week' => Weekday::Friday,
        'lesson_number' => 7,
    ]);

    lessonsSchedule($class);

    $this->artisan('app:import-lessons')->assertSuccessful();

    expect(Lesson::query()->where('school_class_id', $class->id)->count())->toBe(1)
        ->and(Lesson::query()->where('school_class_id', $class->id)->first()?->id)->toBe($manual->id);
});
