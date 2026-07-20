<?php

/**
 * Riscul de amânare: „nu e risc" ≠ „nu pot calcula".
 *
 * Ambiguitatea englezei (nomenclatorul are DOUĂ fișe — „Limba străină 1 (engleza)" și „Limba engleză
 * (opț)" — iar orarele scriu doar „Limba engleză") face ca acele ore să nu intre în orarul
 * structurat. Consecința nu era o eroare, ci o TĂCERE: disciplina dispărea din calcul, iar familia
 * vedea aceeași pagină ca a unui elev fără nicio problemă.
 *
 * Împărțirea orelor între cele două fișe NU se poate deduce din date (măsurat pe baza reală: zilele
 * distincte de notare dau un raport de ~1,36, absențele ~2,04 — două proxy-uri care se contrazic),
 * iar o atribuire ghicită ar schimba cine primește avertismentul. Deci sursa trebuie să distingă;
 * până atunci, golul se ARATĂ.
 */

use App\Actions\ComputeDeferralRisk;
use App\Console\Commands\ImportLessonsFromSchedules;
use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create([
        'is_current' => true,
        'starts_on' => '2026-01-12',
        'ends_on' => '2026-03-20',
    ]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();
});

it('disciplina cu absențe dar fără ore în orar se raportează ca NEDETERMINATĂ, nu se pierde', function () {
    $inOrar = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
    $faraOrar = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'min_grade' => 5, 'max_grade' => 12]);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $inOrar->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    // Elevul chiar lipsește la disciplina care nu e în orar — deci există activitate reală de
    // catalog, dar niciun numitor față de care s-o raportăm.
    Absence::factory()->count(4)->create([
        'student_id' => $this->student->id,
        'subject_id' => $faraOrar->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
    ]);

    $result = app(ComputeDeferralRisk::class)->for($this->student);

    expect($result['undetermined'])->toContain('Limba străină 1 (engleza)')
        // Matematica are orar, deci e evaluabilă — nu apare ca nedeterminată.
        ->and($result['undetermined'])->not->toContain('Matematică');
});

it('elevul fără goluri de orar nu primește nicio mențiune de nedeterminare', function () {
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    Absence::factory()->count(2)->create([
        'student_id' => $this->student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
    ]);

    $result = app(ComputeDeferralRisk::class)->for($this->student);

    expect($result['undetermined'])->toBe([])
        ->and($result['noTimetable'])->toBeFalse();
});

it('clasa fără orar deloc nu ascunde disciplinele: le raportează pe toate ca nedeterminate', function () {
    $subject = Subject::factory()->create(['name' => 'Chimie', 'min_grade' => 7, 'max_grade' => 12]);

    Absence::factory()->count(3)->create([
        'student_id' => $this->student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
    ]);

    $result = app(ComputeDeferralRisk::class)->for($this->student);

    // Înainte, ramura „fără orar" returna listă goală și totul dispărea în tăcere. Acum clasa fără
    // NICIUN slot e semnalată printr-un singur mesaj, nu prin enumerarea tuturor disciplinelor ei —
    // pe date reale asta însemna un zid de 12 denumiri, care se citea ca 12 probleme.
    expect($result['risks'])->toBe([])
        ->and($result['noTimetable'])->toBeTrue()
        ->and($result['undetermined'])->toBe([]);
});

it('REMEDIUL LA SURSĂ: marcarea „(opț)" în orar dezambiguizează singură cele două engleze', function () {
    $l1 = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'min_grade' => 5, 'max_grade' => 12]);
    $opt = Subject::factory()->create(['name' => 'Limba engleză (opț)', 'min_grade' => 5, 'max_grade' => 11]);

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'is_public' => true,
        'school_class_id' => $this->class->id,
        'headers' => ['Ora', 'Luni', 'Marți'],
        'rows' => [[
            'Lecția 1',
            // Ora obligatorie, scrisă cu denumirea din nomenclator.
            'Limba străină 1 (engleza) , gr.1, Moșu A. (s. 23)',
            // Ora opțională, marcată — exact ce trebuie să scrie școala în orarul publicat.
            'Limba engleză (opț) , gr.1, Moșu A. (s. 23)',
        ]],
    ]);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    $peZi = Lesson::query()->where('school_class_id', $this->class->id)->pluck('subject_id', 'day_of_week');

    // Fiecare oră ajunge la fișa ei: numitorul riscului devine corect pentru AMBELE discipline,
    // fără nicio ghicire în cod. Remediul e o editare de text în orarul publicat.
    expect($peZi[Weekday::Monday->value])->toBe($l1->id)
        ->and($peZi[Weekday::Tuesday->value])->toBe($opt->id);
});
