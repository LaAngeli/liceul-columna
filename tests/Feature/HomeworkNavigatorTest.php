<?php

/**
 * Navigatorul de catalog al paginii „Teme" — ADAPTAT modelului temelor: dimensiuni fără
 * „Perioade", clasa contextului tradusă în (treaptă, literă) cu includerea temelor pe toată
 * treapta, agregate mapate pe clase și pre-completarea formularului din context.
 */

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
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

    // Treapta 7 cu literele A și B; profesorul predă doar în 7 A.
    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'grade_level' => 7, 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'grade_level' => 7, 'section' => 'B']);
    $this->subject = Subject::factory()->create();
});

function hwNavTeacher(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

function hwNavDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

function hwFor(int $gradeLevel, ?string $section, Subject $subject, ?Teacher $teacher = null, string $on = '2025-10-10'): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'grade_level' => $gradeLevel,
        'section' => $section,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'teacher_id' => $teacher?->id,
        'author_name' => $teacher?->full_name ?? 'Legacy',
        'assigned_on' => $on,
    ]);
}

// ─── Dimensiuni adaptate temelor ─────────────────────────────────────────────────────────

it('temele nu au dimensiunea „Perioade"; „Profesori" rămâne doar pentru administrație', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));
    $teacherDims = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogDimensions();
    expect($teacherDims)->toHaveKeys(['clase', 'discipline'])
        ->not->toHaveKey('perioade')
        ->not->toHaveKey('profesori');

    actingAs(hwNavDirector());
    $adminDims = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogDimensions();
    expect($adminDims)->toHaveKeys(['clase', 'discipline', 'profesori'])
        ->not->toHaveKey('perioade');
});

// ─── Contextul de clasă = treaptă + literă, cu temele pe toată treapta ───────────────────

it('contextul clasei include temele literei EI și temele pe toată treapta, nu ale altei litere', function () {
    actingAs(hwNavDirector());

    $forA = hwFor(7, 'A', $this->subject);
    $wholeGrade = hwFor(7, null, $this->subject);
    $forB = hwFor(7, 'B', $this->subject);

    Livewire::test(ListHomeworkAssignments::class)
        ->call('openCatalogEntity', $this->classA->id)
        ->assertCanSeeTableRecords([$forA, $wholeGrade])
        ->assertCanNotSeeTableRecords([$forB]);
});

it('cardul clasei agregă și temele pe toată treapta (numărul include litera + treapta)', function () {
    actingAs(hwNavDirector());

    hwFor(7, 'A', $this->subject, on: '2025-10-01');
    hwFor(7, null, $this->subject, on: '2025-11-05');

    $cards = collect(Livewire::test(ListHomeworkAssignments::class)->instance()->catalogEntityCards());

    $cardA = $cards->firstWhere('id', $this->classA->id);
    $cardB = $cards->firstWhere('id', $this->classB->id);

    // 7 A: tema ei + cea pe treaptă = 2; 7 B: doar cea pe treaptă = 1.
    expect($cardA['stats'])->toContain(trans_choice('panel.catalog_nav.homework_records', 2, ['count' => 2]))
        ->and($cardB['stats'])->toContain(trans_choice('panel.catalog_nav.homework_records', 1, ['count' => 1]));
});

it('profesorul vede doar clasele lui drept carduri (teme)', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    $cards = Livewire::test(ListHomeworkAssignments::class)->instance()->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->classA->id]);
});

it('un ?perioada= rătăcit în URL nu deschide niciun context la teme', function () {
    actingAs(hwNavDirector());

    $component = Livewire::withQueryParams(['vedere' => 'perioade', 'perioada' => '1'])
        ->test(ListHomeworkAssignments::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse()
        // Dimensiunea invalidă cade pe „clase".
        ->and($component->instance()->catalogActiveDimension())->toBe('clase');
});

// ─── Pre-completarea formularului din context (clasa → treaptă + literă) ─────────────────

it('formularul de temă se pre-completează cu treapta, litera și disciplina din context', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id, 'disciplina' => (string) $this->subject->id])
        ->test(CreateHomeworkAssignment::class)
        ->assertFormSet([
            'grade_level' => 7,
            'section' => 'A',
            'subject_id' => $this->subject->id,
        ]);
});

it('o clasă din afara scope-ului profesorului nu se pre-completează în formularul de temă', function () {
    actingAs(hwNavTeacher($this->classA, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->classB->id])
        ->test(CreateHomeworkAssignment::class)
        ->assertFormSet([
            'grade_level' => null,
            'section' => null,
        ]);
});
