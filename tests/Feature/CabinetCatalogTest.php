<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
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
 * Părinte cu un copil înscris într-o clasă.
 *
 * @return array{0: User, 1: Student}
 */
function catalogFamily(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$parent, $student];
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
            ->has('subjects')
            // Modulul încarcă DOAR datele lui — nimic din celelalte module.
            ->missing('homework')
            ->missing('timetable')
            ->missing('absencesBySubject'));
});

it('secțiunea „medii" încarcă matricea și NU notele curente', function () {
    [$parent] = catalogFamily();

    $this->actingAs($parent)->get(route('cabinet.grades', ['sectiune' => 'medii']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'medii')
            ->where('subjects', null)
            ->has('averages'));
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
            ->has('absencesBySubject')
            ->where('motivations', null));

    $this->actingAs($parent)->get(route('cabinet.absences', ['sectiune' => 'motivari']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.section', 'motivari')
            ->has('motivations')
            ->where('canRequestMotivation', true)
            ->where('absencesBySubject', null));
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
