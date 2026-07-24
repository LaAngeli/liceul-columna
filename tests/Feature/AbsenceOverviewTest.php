<?php

use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * SITUAȚIA absențelor (modulul „Absențe"): regulile care decid ce vede familia.
 *
 * Cele mai multe teste de aici apără câte o inferență: lecția se deduce din orar, dar tace când
 * orarul e ambiguu; profesorul vine din alocări, fiindcă rândul nu-l poartă; „zilele" nu sunt
 * același lucru cu „absențele"; tendința se citește invers față de medii, pentru că la absențe
 * mai mult înseamnă mai rău.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->withoutVite();
});

/**
 * Familie + an cu două semestre (al doilea curent).
 *
 * @return array{parent: User, student: Student, class: SchoolClass, sem1: Term, sem2: Term}
 */
function absenceFixture(): array
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

/** O absență, fără ceremonie. */
function absenceOn(array $fx, ?Subject $subject, Term $term, string $date, bool $motivated = false, array $extra = []): Absence
{
    return Absence::factory()->create(array_merge([
        'student_id' => $fx['student']->id,
        'subject_id' => $subject?->id,
        'school_class_id' => $fx['class']->id,
        'term_id' => $term->id,
        'teacher_id' => null,
        'occurred_on' => $date,
        'is_motivated' => $motivated,
        'motivation_deadline' => null,
    ], $extra));
}

it('separă semestrele: fiecare cu absențele, sinteza și evoluția lui', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Matematică']);

    absenceOn($fx, $subject, $fx['sem1'], '2025-10-01');
    absenceOn($fx, $subject, $fx['sem1'], '2025-11-03');
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-02');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.currentTerm', 2)
            ->has('overview.terms', 2)
            ->has('overview.absences', 3)
            ->where('overview.summary.1.total', 2)
            ->where('overview.summary.2.total', 1)
            ->where('overview.subjects.0.terms.1.total', 2)
            ->where('overview.subjects.0.terms.2.total', 1));
});

it('numără ZILELE lipsite, nu doar absențele: trei lecții într-o zi înseamnă o zi', function () {
    $fx = absenceFixture();
    $a = Subject::factory()->create(['name' => 'Matematică']);
    $b = Subject::factory()->create(['name' => 'Fizică']);
    $c = Subject::factory()->create(['name' => 'Chimie']);

    foreach ([$a, $b, $c] as $subject) {
        absenceOn($fx, $subject, $fx['sem2'], '2026-02-02');
    }
    absenceOn($fx, $a, $fx['sem2'], '2026-02-09');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.summary.2.total', 4)
            // Patru absențe, dar DOUĂ zile — întrebarea părintelui e „câte zile a lipsit".
            ->where('overview.summary.2.days', 2)
            ->where('overview.summary.2.subjectsCount', 3));
});

it('împarte motivat/nemotivat și calculează procentul care alimentează bara', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Istorie']);

    absenceOn($fx, $subject, $fx['sem2'], '2026-02-02', motivated: true);
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-03', motivated: true);
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-04');
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-05');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.summary.2.motivated', 2)
            ->where('overview.summary.2.unmotivated', 2)
            ->where('overview.summary.2.motivatedRate', 50)
            ->where('overview.subjects.0.terms.2.motivated', 2)
            ->where('overview.subjects.0.terms.2.unmotivated', 2));
});

it('la absențe tendința se citește INVERS: mai multe decât semestrul trecut = „down"', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Biologie']);

    absenceOn($fx, $subject, $fx['sem1'], '2025-10-01');
    foreach (['2026-02-02', '2026-02-03', '2026-02-04'] as $date) {
        absenceOn($fx, $subject, $fx['sem2'], $date);
    }

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.summary.1.trend', null)
            ->where('overview.summary.1.previousTotal', null)
            // 3 > 1 → s-a înrăutățit; UI-ul colorează `down` în roșu, ca la medii.
            ->where('overview.summary.2.trend', 'down')
            ->where('overview.summary.2.previousTotal', 1));
});

it('deduce lecția din orar, dar TACE când disciplina apare de două ori în aceeași zi', function () {
    $fx = absenceFixture();
    $clear = Subject::factory()->create(['name' => 'Informatică']);
    $twice = Subject::factory()->create(['name' => 'Sport']);

    // 2026-02-02 e o luni.
    Lesson::create([
        'academic_year_id' => $fx['class']->academic_year_id, 'school_class_id' => $fx['class']->id,
        'subject_id' => $clear->id, 'day_of_week' => Weekday::Monday, 'lesson_number' => 3, 'room' => '204',
    ]);
    // Aceeași disciplină de două ori luni: nu se poate ști la care lecție a lipsit.
    foreach ([1, 5] as $number) {
        Lesson::create([
            'academic_year_id' => $fx['class']->academic_year_id, 'school_class_id' => $fx['class']->id,
            'subject_id' => $twice->id, 'day_of_week' => Weekday::Monday, 'lesson_number' => $number,
        ]);
    }

    absenceOn($fx, $twice, $fx['sem2'], '2026-02-02');
    absenceOn($fx, $clear, $fx['sem2'], '2026-02-02');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $absences = collect($page->toArray()['props']['overview']['absences']);
            $lessons = $absences->keyBy('subject')->map(fn (array $row): ?array => $row['lesson']);

            expect($lessons['Informatică'])->toBe(['number' => 3, 'room' => '204'])
                ->and($lessons['Sport'])->toBeNull();
        });
});

it('lecția rămâne goală în ziua în care disciplina nu e în orar', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Geografie']);

    Lesson::create([
        'academic_year_id' => $fx['class']->academic_year_id, 'school_class_id' => $fx['class']->id,
        'subject_id' => $subject->id, 'day_of_week' => Weekday::Monday, 'lesson_number' => 2,
    ]);

    // 2026-02-03 e marți — disciplina nu se ține atunci, deci nu se inventează un număr.
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-03');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('overview.absences.0.lesson', null));
});

it('profesorul vine din alocare când rândul nu-l poartă, dar tace la alocare ambiguă', function () {
    $fx = absenceFixture();
    $clear = Subject::factory()->create(['name' => 'Chimie']);
    $split = Subject::factory()->create(['name' => 'Limba engleză']);

    $one = Teacher::factory()->create(['last_name' => 'Popescu', 'first_name' => 'Ion']);
    $two = Teacher::factory()->create(['last_name' => 'Ionescu', 'first_name' => 'Ana']);

    TeachingAssignment::create(['teacher_id' => $one->id, 'subject_id' => $clear->id, 'school_class_id' => $fx['class']->id]);
    foreach ([[$one, 1], [$two, 2]] as [$teacher, $group]) {
        TeachingAssignment::create([
            'teacher_id' => $teacher->id, 'subject_id' => $split->id,
            'school_class_id' => $fx['class']->id, 'english_group' => $group,
        ]);
    }

    absenceOn($fx, $clear, $fx['sem2'], '2026-02-02');
    absenceOn($fx, $split, $fx['sem2'], '2026-02-03');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $teachers = collect($page->toArray()['props']['overview']['absences'])
                ->keyBy('subject')
                ->map(fn (array $row): ?string => $row['teacher']);

            expect($teachers['Chimie'])->toBe('Popescu Ion')
                ->and($teachers['Limba engleză'])->toBeNull();
        });
});

it('profesorul CONSEMNAT pe rând bate alocarea (el a fost la lecție)', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Fizică']);
    $titular = Teacher::factory()->create(['last_name' => 'Popescu', 'first_name' => 'Ion']);
    $suplinitor = Teacher::factory()->create(['last_name' => 'Munteanu', 'first_name' => 'Vera']);

    TeachingAssignment::create(['teacher_id' => $titular->id, 'subject_id' => $subject->id, 'school_class_id' => $fx['class']->id]);
    absenceOn($fx, $subject, $fx['sem2'], '2026-02-02', extra: ['teacher_id' => $suplinitor->id]);

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('overview.absences.0.teacher', 'Munteanu Vera'));
});

it('semnalează termenele care expiră în cel mult o săptămână și consolidările', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Matematică']);

    absenceOn($fx, $subject, $fx['sem2'], Carbon::now()->subDays(3)->toDateString(), extra: [
        'motivation_deadline' => Carbon::now()->addDays(2)->toDateString(),
    ]);
    absenceOn($fx, $subject, $fx['sem2'], Carbon::now()->subDays(40)->toDateString(), extra: [
        'motivation_deadline' => Carbon::now()->subDays(30)->toDateString(),
    ]);
    absenceOn($fx, $subject, $fx['sem2'], Carbon::now()->subDays(50)->toDateString(), extra: [
        'motivation_locked_at' => Carbon::now()->subDays(20),
    ]);

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.summary.2.expiringSoon', 1)
            // Termen depășit + închisă de diriginte = două absențe definitive.
            ->where('overview.summary.2.locked', 2));
});

it('seria lunară acoperă și lunile fără absențe dintre prima și ultima', function () {
    $fx = absenceFixture();
    $subject = Subject::factory()->create(['name' => 'Istorie']);

    absenceOn($fx, $subject, $fx['sem2'], '2026-01-15');
    absenceOn($fx, $subject, $fx['sem2'], '2026-04-20');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $months = $page->toArray()['props']['overview']['months']['2'];

            // Ianuarie → aprilie: fără februarie și martie la zero, graficul ar comprima trei luni
            // de pauză într-un singur pas și ar minți despre ritm.
            expect(array_column($months, 'key'))->toBe(['2026-01', '2026-02', '2026-03', '2026-04'])
                ->and(array_column($months, 'total'))->toBe([1, 0, 0, 1]);
        });
});

it('absența pe zi întreagă (fără disciplină) primește grupul ei, nu dispare', function () {
    $fx = absenceFixture();
    absenceOn($fx, null, $fx['sem2'], '2026-02-02');

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.absences', 1)
            ->where('overview.absences.0.subjectId', 0)
            ->where('overview.absences.0.lesson', null)
            ->where('overview.subjects.0.id', 0));
});

it('situația altui copil rămâne inaccesibilă (403)', function () {
    $fx = absenceFixture();
    $stranger = Student::factory()->create();

    $this->actingAs($fx['parent'])
        ->get(route('cabinet.absences', ['copil' => $stranger->id]))
        ->assertForbidden();
});

it('fără semestru curent situația e goală, nu crapă', function () {
    $fx = absenceFixture();
    absenceOn($fx, Subject::factory()->create(), $fx['sem2'], '2026-02-02');
    Term::query()->update(['is_current' => false]);

    $this->actingAs($fx['parent'])->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.currentTerm', null)
            ->where('overview.absences', [])
            ->where('overview.summary', []));
});
