<?php

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    // Randarea paginilor include @vite; fără manifest ar arunca ViteException.
    $this->withoutVite();
});

/**
 * Părinte cu un copil înscris într-o clasă, într-un an cu semestru curent (fără el, modulele
 * scopate pe semestru — Note și Absențe — n-au axă și întorc, corect, gol).
 *
 * @return array{0: User, 1: Student, 2: Term, 3: SchoolClass}
 */
function catalogFamily(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $term = Term::factory()->for($year)->create(['number' => 1, 'is_current' => true]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$parent, $student, $term, $class];
}

it('modulul Note se randează pentru părinte cu datele DOAR ale modulului', function () {
    [$parent, $student] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/note')
            ->where('module.section', 'curente')
            ->where('module.currentId', $student->id)
            ->has('module.students', 1)
            // Switcher-ul de copil poartă PRENUMELE (părintele diferențiază după prenume),
            // nu numele complet — garda de regresie pentru afișarea greșită a numelui de familie.
            ->where('module.students.0.firstName', $student->first_name)
            ->has('gradebook')
            // Modulul încarcă DOAR datele lui — nimic din celelalte module.
            ->missing('homework')
            ->missing('weekly')
            ->missing('register'));
});

it('secțiunea „evoluție" încarcă traseul și NU catalogul semestrului', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.grades', ['sectiune' => 'evolutie']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'evolutie')
            ->where('gradebook', null)
            ->has('evolution.matrix')
            ->has('evolution.dynamics'));
});

it('vechiul link „medii" duce la evoluție, nu la secțiunea implicită', function () {
    [$parent] = catalogFamily();

    // Notificările și semnele de carte emise înainte de redenumire trebuie să aterizeze pe
    // conținutul echivalent, nu pe „Note curente".
    $this->actingAs($parent)->get(route('cabinet.grades', ['sectiune' => 'medii']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'evolutie')
            ->has('evolution'));
});

it('o secțiune necunoscută cade pe secțiunea implicită', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.grades', ['sectiune' => 'inexistenta']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('module.section', 'curente'));
});

it('modulul Absențe: registru implicit, motivările în secțiunea lor', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/absente')
            ->where('module.section', 'registru')
            ->has('overview.subjects')
            ->has('overview.absences')
            ->has('overview.summary')
            ->where('motivations', null));

    $this->actingAs($parent)->get(route('cabinet.absences', ['sectiune' => 'motivari']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'motivari')
            ->has('motivations')
            ->where('canRequestMotivation', true)
            ->where('overview', null));
});

it('situația expune contextul complet al absenței: profesor, statut, termen, disciplină', function () {
    [$parent, $student, $term, $class] = catalogFamily();

    $subject = Subject::factory()->create(['name' => 'Matematica']);
    $teacher = Teacher::factory()->create(['last_name' => 'Popescu', 'first_name' => 'Ion']);
    Absence::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
        'teacher_id' => $teacher->id,
        'occurred_on' => Carbon::yesterday()->toDateString(),
        'is_motivated' => false,
        'motivation_deadline' => Carbon::tomorrow()->toDateString(),
    ]);

    $this->actingAs($parent)->get(route('cabinet.absences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.absences.0.subject', 'Matematica')
            ->where('overview.absences.0.teacher', 'Popescu Ion')
            ->where('overview.absences.0.motivated', false)
            ->where('overview.absences.0.date', Carbon::yesterday()->format('d.m.Y'))
            ->where('overview.absences.0.deadline', Carbon::tomorrow()->format('d.m.Y'))
            ->where('overview.absences.0.deadlinePassed', false)
            ->where('overview.absences.0.locked', false)
            ->where('overview.subjects.0.name', 'Matematica')
            ->where('overview.subjects.0.terms.1.unmotivated', 1)
            ->where('overview.summary.1.unmotivated', 1));
});

it('cererea de motivare poartă cronologia completă: depunere, decizie, impact', function () {
    [$parent, $student] = catalogFamily();

    $reviewer = User::factory()->create(['name' => 'Diriginte Demo']);
    Absence::factory()->create([
        'student_id' => $student->id,
        'occurred_on' => Carbon::yesterday()->toDateString(),
        'is_motivated' => true,
    ]);
    $motivation = AbsenceMotivation::create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
        'reason' => 'Consultație medicală',
        'period_start' => Carbon::yesterday()->toDateString(),
        'period_end' => Carbon::yesterday()->toDateString(),
        'status' => RequestStatus::Pending,
    ]);
    $motivation->approve($reviewer->id, 'Justificat.');

    $this->actingAs($parent)->get(route('cabinet.absences', ['sectiune' => 'motivari']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('motivations.0.status', 'approved')
            ->where('motivations.0.submittedBy', $parent->name)
            ->where('motivations.0.decidedBy', 'Diriginte Demo')
            ->whereNot('motivations.0.decidedAt', null)
            ->whereNot('motivations.0.submittedAt', null)
            ->where('motivations.0.note', 'Justificat.')
            ->where('motivations.0.absencesTotal', 1)
            ->where('motivations.0.absencesUnmotivated', 0));
});

it('fereastra de motivare urmează anul școlar curent', function () {
    // Semestrul curent e cel al fixturii — un al doilea „is_current" ar face ambiguu care an
    // dictează fereastra, exact situația pe care garda din model o interzice oricum.
    [$parent, , $term] = catalogFamily();
    $term->update(['starts_on' => '2025-09-01', 'ends_on' => '2026-01-31']);

    $this->actingAs($parent)->get(route('cabinet.absences', ['sectiune' => 'motivari']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('motivationWindow.min', '2025-09-01')
            ->whereNot('motivationWindow.max', null));
});

it('modulul Orar aduce temele DOAR în secțiunea „Ziua mea"', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/orar')
            ->where('module.section', 'zi')
            ->has('homework'));

    $this->actingAs($parent)->get(route('cabinet.schedule', ['sectiune' => 'saptamana']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'saptamana')
            ->where('homework', null));
});

it('modulul Teme se randează cu lista temelor', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.homework'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/teme')
            ->has('homework'));
});

it('tema cu termen AZI e „today" și noaptea, când ziua UTC e încă cea precedentă', function () {
    [$parent, $student] = catalogFamily();
    $class = $student->currentSchoolClass();

    // 01:30 ora Chișinăului = 22:30 UTC în ziua PRECEDENTĂ. Cu `today()` (UTC), tema de azi
    // cădea în „upcoming", iar fereastra „De predat în această zi" rămânea goală.
    Carbon::setTestNow(Carbon::parse('2026-03-12 01:30', SchoolCalendar::TIMEZONE));

    HomeworkAssignment::query()->create([
        'subject_name' => 'Matematică',
        'author_name' => 'Damian Iu.',
        'grade_level' => $class->grade_level,
        'section' => $class->section,
        'assigned_on' => '2026-03-10',
        'due_on' => '2026-03-12',
        'topic' => 'Recapitulare',
        'required_task' => 'Ex. 1–3',
    ]);

    $this->actingAs($parent)->get(route('cabinet.homework'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('homework.0.status', 'today')
            ->where('homework.0.effectiveDate', '2026-03-12')
            // Cine a dat tema — snapshot-ul author_name, vizibil familiei pe card.
            ->where('homework.0.teacher', 'Damian Iu.'));

    Carbon::setTestNow();
});

it('elevul își vede propriile module (un singur „copil": el însuși)', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $user->id]);
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $this->actingAs($user)->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('module.students', 1)
            ->where('module.currentId', $student->id));
});

it('personalul e redirecționat către panou (EnsureFamilyCabinet)', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);

    $this->actingAs($staff)->get(route('cabinet.grades'))->assertRedirect('/admin');
    $this->actingAs($staff)->get(route('cabinet.homework'))->assertRedirect('/admin');
});

it('un copil din afara familiei → 403', function () {
    [$parent] = catalogFamily();
    $strain = Student::factory()->create();

    $this->actingAs($parent)->get(route('cabinet.grades', ['copil' => $strain->id]))->assertForbidden();
    $this->actingAs($parent)->get(route('cabinet.absences', ['copil' => $strain->id]))->assertForbidden();
});

it('părintele cu doi copii comută prin ?copil=', function () {
    [$parent, $first] = catalogFamily();
    $second = Student::factory()->create();
    $parent->students()->attach($second->id);

    $this->actingAs($parent)->get(route('cabinet.grades', ['copil' => $second->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('module.students', 2)
            ->where('module.currentId', $second->id));

    // Fără parametru → primul copil (implicit stabil), nu o eroare.
    $this->actingAs($parent)->get(route('cabinet.grades'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('module.currentId', fn ($id) => in_array($id, [$first->id, $second->id], true)));
});
