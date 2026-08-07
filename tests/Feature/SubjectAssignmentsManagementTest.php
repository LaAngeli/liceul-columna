<?php

/**
 * FIȘA DISCIPLINEI SE EDITEAZĂ ÎN TOTALITATE (cerința beneficiarului, 07.08.2026: „după creare
 * nu mai poți modifica profesorii, clasele") — managerul de alocări dinspre disciplină:
 * adaugi (clasă de pe treptele marcate + profesor), retragi (soft delete, istoricul notelor
 * rămâne), restaurezi. Aceleași reguli ca pe fișa persoanei, alt punct de intrare.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->subject = Subject::factory()->create(['name' => 'Fizică', 'grade_levels' => range(6, 12)]);
    $this->teacher = Teacher::factory()->create();
    $this->clasa7 = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);
    $this->clasa2 = SchoolClass::factory()->for($this->year)->create(['grade_level' => 2, 'section' => 'A']);
});

function subjectAssignmentsManager(Subject $subject): Testable
{
    return Livewire::test(TeachingAssignmentsRelationManager::class, [
        'ownerRecord' => $subject,
        'pageClass' => EditSubject::class,
    ]);
}

it('pagina de editare se randează ÎNTREAGĂ, cu managerul de alocări cu tot', function () {
    // GET-ul real al paginii — o eroare de descoperire/înregistrare a relation manager-ului
    // apare DOAR aici, nu în testele Livewire izolate pe componentă. Eticheta tradusă stă în
    // snapshotul Livewire cu unicode ESCAPAT (Alocări), deci se asertează numele montat
    // al componentei, nu textul.
    $this->get(SubjectResource::getUrl('edit', ['record' => $this->subject]))
        ->assertOk()
        ->assertSee('teaching-assignments-relation-manager');
});

it('adaugă o alocare (profesor × clasă) direct de pe fișa disciplinei', function () {
    subjectAssignmentsManager($this->subject)
        ->callTableAction('create', data: [
            'school_class_id' => $this->clasa7->id,
            'teacher_id' => $this->teacher->id,
        ])
        ->assertHasNoTableActionErrors();

    expect(TeachingAssignment::query()
        ->where('subject_id', $this->subject->id)
        ->where('school_class_id', $this->clasa7->id)
        ->where('teacher_id', $this->teacher->id)
        ->exists())->toBeTrue();
});

it('refuză clasa de pe o treaptă NEMARCATĂ — fișa altui ciclu nu se leagă', function () {
    subjectAssignmentsManager($this->subject)
        ->callTableAction('create', data: [
            'school_class_id' => $this->clasa2->id, // treapta 2, disciplina e 6–12
            'teacher_id' => $this->teacher->id,
        ])
        ->assertHasTableActionErrors(['school_class_id']);

    expect(TeachingAssignment::query()->where('subject_id', $this->subject->id)->exists())->toBeFalse();
});

it('refuză duplicatul, iar pe cel ARHIVAT îndrumă spre restaurare', function () {
    $assignment = TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->clasa7->id,
        'teacher_id' => $this->teacher->id,
    ]);

    subjectAssignmentsManager($this->subject)
        ->callTableAction('create', data: [
            'school_class_id' => $this->clasa7->id,
            'teacher_id' => $this->teacher->id,
        ])
        ->assertHasTableActionErrors(['teacher_id']);

    $assignment->delete();

    subjectAssignmentsManager($this->subject)
        ->callTableAction('create', data: [
            'school_class_id' => $this->clasa7->id,
            'teacher_id' => $this->teacher->id,
        ])
        ->assertHasTableActionErrors(['teacher_id']);

    expect(TeachingAssignment::withTrashed()->where('subject_id', $this->subject->id)->count())->toBe(1);
});

it('retrage și restaurează alocarea — registrul păstrează istoricul', function () {
    $assignment = TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->clasa7->id,
        'teacher_id' => $this->teacher->id,
    ]);

    subjectAssignmentsManager($this->subject)
        ->callTableAction('delete', $assignment)
        ->assertHasNoTableActionErrors();

    expect($assignment->refresh()->trashed())->toBeTrue();

    subjectAssignmentsManager($this->subject)
        ->filterTable('trashed', true)
        ->callTableAction('restore', $assignment)
        ->assertHasNoTableActionErrors();

    expect($assignment->refresh()->trashed())->toBeFalse();
});

it('clasele oferite vin DOAR de pe treptele marcate ale disciplinei', function () {
    $manager = new ReflectionMethod(TeachingAssignmentsRelationManager::class, 'classOptions');
    $manager->setAccessible(true);

    $instance = new TeachingAssignmentsRelationManager;
    $instance->ownerRecord = $this->subject;

    $options = $manager->invoke($instance);

    expect($options)->toHaveKey($this->clasa7->id)
        ->and($options)->not->toHaveKey($this->clasa2->id);
});

it('grupa apare doar la limba engleză — disciplina fixă decide o singură dată', function () {
    $engleza = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'grade_levels' => range(5, 12)]);
    $clasa = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'section' => 'B']);

    subjectAssignmentsManager($engleza)
        ->callTableAction('create', data: [
            'school_class_id' => $clasa->id,
            'teacher_id' => $this->teacher->id,
            'english_group' => 2,
        ])
        ->assertHasNoTableActionErrors();

    expect(TeachingAssignment::query()
        ->where('subject_id', $engleza->id)
        ->where('english_group', 2)
        ->exists())->toBeTrue();

    // Pe o disciplină NE-engleză grupa nu se dehidratează — chiar trimisă, nu se scrie.
    subjectAssignmentsManager($this->subject)
        ->callTableAction('create', data: [
            'school_class_id' => $this->clasa7->id,
            'teacher_id' => $this->teacher->id,
            'english_group' => 2,
        ])
        ->assertHasNoTableActionErrors();

    expect(TeachingAssignment::query()
        ->where('subject_id', $this->subject->id)
        ->value('english_group'))->toBeNull();
});
