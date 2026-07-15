<?php

/**
 * Navigatorul paginii „Elevi" — adaptat: singura dimensiune e „Clase" (elevul se leagă de clasă
 * prin ÎNMATRICULARE), iar administrația păstrează registrul complet prin vederea „Arhivă".
 */

use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->ownClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ST-A', 'section' => null]);
    $this->foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ST-B', 'section' => null]);
    $this->subject = Subject::factory()->create();

    $this->ownStudent = Student::factory()->create();
    Enrollment::factory()->for($this->ownStudent)->for($this->ownClass)->for($this->year)->create();
    $this->foreignStudent = Student::factory()->create();
    Enrollment::factory()->for($this->foreignStudent)->for($this->foreignClass)->for($this->year)->create();

    // Elev PLECAT: fără nicio înmatriculare — invizibil pe carduri, dar prezent în Arhivă.
    $this->orphanStudent = Student::factory()->create();
});

function studentsNavTeacher(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

function studentsNavDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

it('elevii au o singură dimensiune (Clase), cu carduri doar din perimetrul profesorului', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::test(ListStudents::class);

    expect($component->instance()->catalogDimensions())->toHaveKey('clase')->toHaveCount(1)
        ->and(collect($component->instance()->catalogEntityCards())->pluck('id')->all())->toBe([$this->ownClass->id]);
});

it('contextul clasei arată DOAR elevii înmatriculați în ea', function () {
    actingAs(studentsNavDirector());

    Livewire::test(ListStudents::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$this->ownStudent])
        ->assertCanNotSeeTableRecords([$this->foreignStudent, $this->orphanStudent]);
});

it('clasa străină venită prin URL nu deschide context pentru profesor', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['clasa' => (string) $this->foreignClass->id])
        ->test(ListStudents::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

it('ARHIVA (administrație): registrul complet, inclusiv elevii fără înmatriculare', function () {
    actingAs(studentsNavDirector());

    Livewire::withQueryParams(['arhiva' => '1'])
        ->test(ListStudents::class)
        ->assertCanSeeTableRecords([$this->ownStudent, $this->foreignStudent, $this->orphanStudent]);
});

it('ARHIVA nu e accesibilă profesorului (flag-ul din URL se ignoră)', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['arhiva' => '1'])->test(ListStudents::class);

    // Fără context (navigatorul rămâne pe carduri) — arhiva cere isAdministrator.
    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

it('ieșirea din context curăță și flag-ul de arhivă', function () {
    actingAs(studentsNavDirector());

    $component = Livewire::withQueryParams(['arhiva' => '1'])->test(ListStudents::class);
    expect($component->instance()->hasCatalogContext())->toBeTrue();

    $component->call('leaveCatalogContext');
    expect($component->instance()->hasCatalogContext())->toBeFalse();
});
