<?php

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * CATALOGUL familiei (modulul „Note"): regulile care decid ce vede părintele.
 *
 * Testele pin-uiesc exact defectele pe care restructurarea le-a reparat — scoparea pe semestru și
 * media oficială — plus garanțiile care nu au voie să alunece: nota anulată nu apare, calificativul
 * nu se transformă în cantitate, iar un profesor nu e numit acolo unde nu se știe cine e.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->withoutVite();
});

/**
 * Familie + an cu DOUĂ semestre (al doilea curent) — contextul minim în care are sens un catalog.
 *
 * @return array{parent: User, student: Student, class: SchoolClass, sem1: Term, sem2: Term}
 */
function gradeBookFixture(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $sem1 = Term::factory()->for($year)->create(['number' => 1, 'name' => 'Semestrul I', 'is_current' => false]);
    $sem2 = Term::factory()->for($year)->create(['number' => 2, 'name' => 'Semestrul II', 'is_current' => true]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return ['parent' => $parent, 'student' => $student, 'class' => $class, 'sem1' => $sem1, 'sem2' => $sem2];
}

/** O notă, fără ceremonie. */
function gradeBookGrade(array $fx, Subject $subject, Term $term, string $date, ?float $value, array $extra = []): Grade
{
    return Grade::factory()->create(array_merge([
        'student_id' => $fx['student']->id,
        'subject_id' => $subject->id,
        'school_class_id' => $fx['class']->id,
        'term_id' => $term->id,
        'teacher_id' => null,
        'graded_on' => $date,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => $value,
        'calificativ' => null,
    ], $extra));
}

it('notele NU se mai amestecă între semestre: fiecare semestru își are notele și sinteza lui', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Matematică']);

    gradeBookGrade($fx, $subject, $fx['sem1'], '2025-10-01', 7);
    gradeBookGrade($fx, $subject, $fx['sem1'], '2025-11-01', 8);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-01', 10);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.currentTerm', 2)
            ->has('gradebook.terms', 2)
            ->has('gradebook.grades', 3)
            // Contorul fiecărui semestru numără DOAR notele lui — bug-ul vechi era că toate
            // notele anului stăteau sub media unui singur semestru.
            ->where('gradebook.summary.1.gradesCount', 2)
            ->where('gradebook.summary.2.gradesCount', 1)
            ->where('gradebook.subjects.0.terms.1.count', 2)
            ->where('gradebook.subjects.0.terms.2.count', 1));
});

it('fiecare notă își poartă data, ziua și tipul — nu doar valoarea', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Istorie']);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-10', 9);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.grades.0.label', '9')
            ->where('gradebook.grades.0.value', 9)
            ->where('gradebook.grades.0.date', '10.02.2026')
            ->where('gradebook.grades.0.iso', '2026-02-10')
            ->where('gradebook.grades.0.weekday', 'marți')
            ->where('gradebook.grades.0.term', 2)
            ->where('gradebook.grades.0.isSummative', false));
});

it('media afișată e cea OFICIALĂ din term_averages, nu o medie aritmetică recalculată', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Fizică']);

    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-01', 10);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-02', 10);

    // MS oficială diferă deliberat de media notelor (teza ponderată 50% — §2.4): dacă UI-ar
    // recalcula din note, ar arăta 10 și ar contrazice catalogul.
    TermAverage::create([
        'student_id' => $fx['student']->id,
        'subject_id' => $subject->id,
        'school_class_id' => $fx['class']->id,
        'term_id' => $fx['sem2']->id,
        'value' => 8.5,
        'mc_value' => 10,
        'summative_value' => 7,
    ]);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.subjects.0.terms.2.average', 8.5)
            ->where('gradebook.subjects.0.terms.2.mc', 10)
            ->where('gradebook.subjects.0.terms.2.summative', 7)
            ->where('gradebook.summary.2.average', 8.5));
});

it('nota anulată dispare din catalog: nici în listă, nici la contoare (§1)', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Chimie']);

    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-01', 9);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-02', 4, [
        'annulled_at' => now(),
        'annulment_reason' => 'Introdusă la elevul greșit',
    ]);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('gradebook.grades', 1)
            ->where('gradebook.grades.0.label', '9')
            ->where('gradebook.summary.2.gradesCount', 1));
});

it('media sub 5 e semnalată ca restanță, în sinteză și pe disciplină', function () {
    $fx = gradeBookFixture();
    $weak = Subject::factory()->create(['name' => 'Biologie']);
    $ok = Subject::factory()->create(['name' => 'Geografie']);

    gradeBookGrade($fx, $weak, $fx['sem2'], '2026-02-01', 4);
    gradeBookGrade($fx, $ok, $fx['sem2'], '2026-02-01', 9);

    foreach ([[$weak, 4.33], [$ok, 9.0]] as [$subject, $value]) {
        TermAverage::create([
            'student_id' => $fx['student']->id,
            'subject_id' => $subject->id,
            'school_class_id' => $fx['class']->id,
            'term_id' => $fx['sem2']->id,
            'value' => $value,
        ]);
    }

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.summary.2.riskCount', 1)
            // Media generală = media MS-urilor, TRUNCHIATĂ la sutimi (nu rotunjită): 13,33/2 = 6,665.
            ->where('gradebook.summary.2.average', 6.66)
            ->where('gradebook.subjects.0.terms.2.risk', true)
            ->where('gradebook.subjects.1.terms.2.risk', false));
});

it('graficul arată evoluția MEDIEI, iar săgeata ultima ei mișcare', function () {
    $fx = gradeBookFixture();
    $one = Subject::factory()->create(['name' => 'Arte']);
    $rising = Subject::factory()->create(['name' => 'Zoologie']);

    // O singură notă → media n-are încă istorie: nicio săgeată (o direcție inventată ar minți).
    gradeBookGrade($fx, $one, $fx['sem2'], '2026-02-01', 7);

    // 4 → 4,5 → 6: media URCĂ după fiecare notă.
    foreach ([4, 5, 9] as $i => $value) {
        gradeBookGrade($fx, $rising, $fx['sem2'], '2026-02-0'.($i + 1), $value);
    }

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.subjects.0.terms.2.trend', null)
            ->where('gradebook.subjects.0.terms.2.averageSeries', [7])
            // Media recalculată după FIECARE notă, cronologic ASC.
            ->where('gradebook.subjects.1.terms.2.averageSeries', [4, 4.5, 6])
            ->where('gradebook.subjects.1.terms.2.trend', 'up'));
});

it('capătul graficului ESTE media afișată — inclusiv cu teză, ponderată 50%', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Fizică']);

    // Curente 6 și 8 → MC = 7. Teză 9 → MS = (7 + 9) / 2 = 8.
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-01', 6);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-02', 8);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-03', 9, ['evaluation_type' => EvaluationType::Teza]);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $stats = $page->toArray()['props']['gradebook']['subjects'][0]['terms'][2];

            // Cast explicit: JSON scrie 8.0 ca „8", deci valorile întregi ajung aici ca int.
            // Graficul nu poate contrazice cifra de lângă el: ultimul punct = media oficială.
            expect((float) end($stats['averageSeries']))->toBe((float) $stats['average'])
                ->and((float) $stats['average'])->toBe(8.0)
                // Teza a urcat media de la 7 la 8 → săgeata arată creștere.
                ->and(array_map('floatval', $stats['averageSeries']))->toBe([6.0, 7.0, 8.0])
                ->and($stats['trend'])->toBe('up');
        });
});

it('calificativul apare ca notă, dar nu intră în serie (nu e cantitate)', function () {
    $fx = gradeBookFixture();
    $subject = Subject::factory()->create(['name' => 'Educație muzicală']);

    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-01', null, ['calificativ' => 'FB']);
    gradeBookGrade($fx, $subject, $fx['sem2'], '2026-02-02', null, ['calificativ' => 'B']);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.grades.0.label', 'B')
            ->where('gradebook.grades.0.value', null)
            ->where('gradebook.subjects.0.terms.2.count', 2)
            ->where('gradebook.subjects.0.terms.2.averageSeries', [])
            ->where('gradebook.subjects.0.terms.2.trend', null));
});

it('profesorul disciplinei vine din alocare, dar tace când alocarea e ambiguă', function () {
    $fx = gradeBookFixture();
    $clear = Subject::factory()->create(['name' => 'Informatică']);
    $split = Subject::factory()->create(['name' => 'Limba engleză']);

    $one = Teacher::factory()->create(['last_name' => 'Popescu', 'first_name' => 'Ion']);
    $two = Teacher::factory()->create(['last_name' => 'Ionescu', 'first_name' => 'Ana']);

    TeachingAssignment::create([
        'teacher_id' => $one->id, 'subject_id' => $clear->id, 'school_class_id' => $fx['class']->id,
    ]);
    // Grupe de engleză: doi profesori pe aceeași pereche clasă+disciplină → nu se poate ști care
    // a predat. Un nume greșit lângă o notă e mai rău decât niciun nume.
    foreach ([[$one, 1], [$two, 2]] as [$teacher, $group]) {
        TeachingAssignment::create([
            'teacher_id' => $teacher->id, 'subject_id' => $split->id,
            'school_class_id' => $fx['class']->id, 'english_group' => $group,
        ]);
    }

    gradeBookGrade($fx, $clear, $fx['sem2'], '2026-02-01', 9);
    gradeBookGrade($fx, $split, $fx['sem2'], '2026-02-01', 9);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.subjects.0.name', 'Informatică')
            ->where('gradebook.subjects.0.teachers', ['Popescu Ion'])
            ->where('gradebook.subjects.1.name', 'Limba engleză')
            ->where('gradebook.subjects.1.teachers', []));
});

it('catalogul altui copil rămâne inaccesibil (403), nu se substituie tăcut cu al propriului copil', function () {
    $fx = gradeBookFixture();
    $stranger = Student::factory()->create();

    $this->actingAs($fx['parent'])
        ->get(route('cabinet.grades', ['copil' => $stranger->id]))
        ->assertForbidden();
});

it('fără semestru curent (vacanța dintre ani) catalogul e gol, nu crapă', function () {
    $fx = gradeBookFixture();
    Term::query()->update(['is_current' => false]);

    $this->actingAs($fx['parent'])->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradebook.currentTerm', null)
            ->where('gradebook.terms', [])
            ->where('gradebook.grades', []));
});
