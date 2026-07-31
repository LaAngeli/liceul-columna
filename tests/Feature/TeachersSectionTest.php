<?php

/**
 * CONSOLIDAREA Profesori → Utilizatori (decizia beneficiarului, 2026-07-31): secțiunea
 * „Profesori" a fost eliminată complet; personalul se administrează EXCLUSIV din „Utilizatori" —
 * o persoană = un cont, funcțiile sunt roluri, fișa profesională e un detaliu administrat de pe
 * fișa persoanei (EditUser). Fișierul acoperă noua structură + erorile vechi care NU s-au preluat
 * (grupa engleză pe discipline străine, etichete netraduse).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\AcademicYear;
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

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => now()->subMonth()->toDateString(),
        'ends_on' => now()->addMonths(4)->toDateString(), 'is_current' => true,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    actingAs($this->admin);
});

/**
 * Cont pedagogic cu fișă legată — subiectul consolidării.
 *
 * @return array{0: User, 1: Teacher}
 */
function consolidatedTeacherUser(): array
{
    $user = User::factory()->create(['name' => 'Popescu Ion', 'username' => 'popescu.ion'.fake()->unique()->numberBetween(1, 9999)]);
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create([
        'user_id' => $user->id, 'last_name' => 'Popescu', 'first_name' => 'Ion',
    ]);

    return [$user, $teacher];
}

// ─── Secțiunea „Profesori" NU mai există ────────────────────────────────────────────────

it('registrul vechi „Profesori" a dispărut complet — ruta nu se mai înregistrează', function () {
    $this->get('/admin/teachers')->assertNotFound();
    $this->get('/admin/teachers/create')->assertNotFound();
});

// ─── Alocările — administrate de pe fișa persoanei (RM pe Utilizatori) ─────────────────

it('alocarea se creează din fișa persoanei, prin model (teacher_id explicit)', function () {
    [$user, $teacher] = consolidatedTeacherUser();
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create(['name' => 'Matematică']);

    Livewire::test(TeachingAssignmentsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('create', data: [
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
        ])
        ->assertHasNoTableActionErrors();

    $assignment = TeachingAssignment::query()->sole();

    expect($assignment->teacher_id)->toBe($teacher->id)
        ->and($assignment->english_group)->toBeNull();
});

it('RM-ul de alocări apare doar pe conturile CU fișă pedagogică', function () {
    [$user] = consolidatedTeacherUser();
    $noFiche = User::factory()->create();
    $noFiche->assignRole(UserRole::Parinte->value);

    expect(TeachingAssignmentsRelationManager::canViewForRecord($user, EditUser::class))->toBeTrue()
        ->and(TeachingAssignmentsRelationManager::canViewForRecord($noFiche, EditUser::class))->toBeFalse();
});

it('eticheta de model a alocării e TRADUSĂ — modalul nu mai spune „Teaching Assignment"', function () {
    expect(TeachingAssignmentsRelationManager::getModelLabel())->toBe(__('panel.resources.teaching_assignments.single'))
        ->and((string) TeachingAssignmentsRelationManager::getModelLabel())->not->toContain('Teaching');
});

// ─── Grupa DOAR la limba engleză (eroarea semnalată nu s-a preluat) ─────────────────────

it('garda de model anulează grupa pe orice disciplină în afara limbii engleze', function () {
    [, $teacher] = consolidatedTeacherUser();
    $class = SchoolClass::factory()->for($this->year)->create();
    $math = Subject::factory()->create(['name' => 'Matematică']);
    $english = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)']);

    $polluted = TeachingAssignment::create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
        'subject_id' => $math->id, 'english_group' => 2,
    ]);
    $legit = TeachingAssignment::create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
        'subject_id' => $english->id, 'english_group' => 2,
    ]);

    expect($polluted->refresh()->english_group)->toBeNull()
        ->and($legit->refresh()->english_group)->toBe(2);
});

// ─── O persoană = o identitate: editarea contului scrie fișa ────────────────────────────

it('redenumirea de pe fișa persoanei se propagă pe fișa profesională (nume + sex + email)', function () {
    [$user, $teacher] = consolidatedTeacherUser();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'last_name' => 'Popescu-Nou',
            'first_name' => 'Ion',
            'fiche_sex' => 'm',
            'email' => 'ion.popescu@columna.internal',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $teacher->refresh();

    expect($user->refresh()->name)->toBe('Popescu-Nou Ion')
        ->and($teacher->last_name)->toBe('Popescu-Nou')
        ->and($teacher->sex?->value)->toBe('m')
        ->and($teacher->email)->toBe('ion.popescu@columna.internal');
});

// ─── Dirigenția gestionată la EDITARE (rolul urmează desemnarea) ────────────────────────

it('dirigenția se acordă și se retrage de pe fișa persoanei; rolul Diriginte urmează', function () {
    [$user, $teacher] = consolidatedTeacherUser();
    $classA = SchoolClass::factory()->for($this->year)->create(['section' => 'A']);
    $classB = SchoolClass::factory()->for($this->year)->create(['section' => 'B']);

    // Acordare: clasa liberă intră în coordonare, contul primește rolul Diriginte.
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['homeroom_class_ids' => [$classA->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($classA->refresh()->homeroom_teacher_id)->toBe($teacher->id)
        ->and($user->refresh()->hasRole(UserRole::Diriginte->value))->toBeTrue();

    // Clasa deja OCUPATĂ de altcineva nu poate fi luată prin editare.
    $other = Teacher::factory()->create();
    $classB->update(['homeroom_teacher_id' => $other->id]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['homeroom_class_ids' => [$classA->id, $classB->id]])
        ->call('save');

    expect($classB->refresh()->homeroom_teacher_id)->toBe($other->id);

    // Retragere: lista goală eliberează clasa, iar mono-dirigintele revine Profesor.
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['homeroom_class_ids' => []])
        ->call('save');

    expect($classA->refresh()->homeroom_teacher_id)->toBeNull()
        ->and($user->refresh()->hasRole(UserRole::Diriginte->value))->toBeFalse();
});

// ─── Arhivarea fișei (fosta ștergere din registru) ──────────────────────────────────────

it('fișa cu dirigenție activă NU se arhivează; fără dirigenție da, iar contul rămâne', function () {
    [$user, $teacher] = consolidatedTeacherUser();
    $class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $teacher->id]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('archiveFiche');

    expect($teacher->refresh()->trashed())->toBeFalse();

    $class->update(['homeroom_teacher_id' => null]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('archiveFiche');

    expect($teacher->refresh()->trashed())->toBeTrue()
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

// ─── Navigatorul: fișele fără cont au bucket acționabil ─────────────────────────────────

it('fișele fără cont apar ca bucket în navigator, cu puntea de creare pe fișă', function () {
    $orphan = Teacher::factory()->create(['user_id' => null, 'last_name' => 'Orfan', 'first_name' => 'Vasile']);

    $component = Livewire::test(ListUsers::class);
    $page = $component->instance();

    expect(collect($page->roleCards())->pluck('id')->all())->toContain(ListUsers::FICHES);

    $component->call('openRole', ListUsers::FICHES);

    $cards = $component->instance()->ficheCards();

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['name'])->toBe('Orfan Vasile')
        ->and($cards[0]['createUrl'])->toContain('fisa='.$orphan->id);
});

it('puntea ?fisa= aterizează pe modul „fișă existentă", cu fișa pre-selectată', function () {
    $orphan = Teacher::factory()->create(['user_id' => null]);

    Livewire::withQueryParams(['rol' => UserRole::Profesor->value, 'fisa' => (string) $orphan->id])
        ->test(CreateUser::class)
        ->assertFormSet([
            'teacher_fiche_mode' => UserForm::FICHE_LINK,
            'teacher_id' => $orphan->id,
        ]);
});

// ─── Punțile repunctate ─────────────────────────────────────────────────────────────────

it('disciplina leagă profesorul de fișa PERSOANEI din Utilizatori (nu de registrul dispărut)', function () {
    [$user, $teacher] = consolidatedTeacherUser();
    $subject = Subject::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'school_class_id' => SchoolClass::factory()->for($this->year)->create()->id,
    ]);

    $component = Livewire::withQueryParams(['disciplina' => (string) $subject->id])
        ->test(ListSubjects::class);

    $context = $component->instance()->adminSubjectContext();
    $teachers = collect($context['teachers'] ?? []);

    expect($teachers->pluck('url')->filter()->first())
        ->toBe(UserResource::getUrl('edit', ['record' => $user->id]));
});
