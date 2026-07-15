<?php

/**
 * Crearea temelor — logica REFĂCUTĂ (2026-07-15): clasa se alege dintr-un singur câmp cu ținte
 * reale (`class:{id}` / `grade:{n}`), deci combinațiile treaptă+literă inexistente au devenit
 * structural imposibile (scopul istoric al acestui test, audit-teme #6). Protecția reală e pe
 * server (EnforcesHomeworkScope): perechile (clasă, disciplină) ale profesorului, „toată treapta"
 * doar pentru administrație, autor forțat, conținut obligatoriu, secție NULL (niciodată '').
 */

use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Schemas\HomeworkAssignmentForm;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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

    // Profesorul predă DOAR (7 „1", disciplina lui). Mai există 7 „2" (aceeași treaptă, altă literă).
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => '1']);
    $this->otherClass = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => '2']);
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    $this->foreignSubject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    actingAs($user);
});

function homeworkAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);

    return $user;
}

// ─── Ținta = un singur câmp cu clase reale, scoped pe rol ────────────────────────────────

it('profesorul vede drept ținte DOAR clasele din alocările lui — fără „toată treapta"', function () {
    expect(array_keys(HomeworkAssignmentForm::classTargetOptions()))
        ->toBe(['class:'.$this->class->id]);
});

it('dirigintele FĂRĂ alocare proprie în clasa lui nu o are drept țintă (vede ≠ postează)', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);
    $homeroomOnly = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9, 'section' => 'A', 'homeroom_teacher_id' => $this->teacher->id]);

    // 9 A e clasa lui de diriginție, dar nu predă nimic acolo → nu poate fi autor de teme.
    expect(array_keys(HomeworkAssignmentForm::classTargetOptions()))
        ->toBe(['class:'.$this->class->id]);
});

it('administrația are drept ținte clasele anului curent ȘI „toată treapta"', function () {
    actingAs(homeworkAdmin());

    $keys = array_keys(HomeworkAssignmentForm::classTargetOptions());

    expect($keys)->toContain('class:'.$this->class->id)
        ->toContain('class:'.$this->otherClass->id)
        ->toContain('grade:7');
});

it('cascadă: disciplinele profesorului pentru ținta aleasă = strict perechile din alocări', function () {
    expect(array_keys(HomeworkAssignmentForm::subjectOptionsFor('class:'.$this->class->id)))
        ->toBe([$this->subject->id]);
});

// ─── Crearea validă: derivarea pe server + autorul forțat ────────────────────────────────

it('crearea pe perechea proprie reușește: treapta+litera derivă din clasă, autorul e forțat', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$this->class->id,
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'required_task' => 'Ex. 1-3, pag. 10',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $homework = HomeworkAssignment::query()->sole();

    expect($homework->grade_level)->toBe(7)
        ->and($homework->section)->toBe('1')
        ->and($homework->teacher_id)->toBe($this->teacher->id)
        ->and($homework->author_name)->toBe($this->teacher->full_name)
        ->and($homework->subject_name)->toBe($this->subject->name);
});

// ─── Protecția pe SERVER — stratul 1: formularul respinge valori din afara opțiunilor ─────
// (Select-urile Filament validează `in:opțiuni` pe server, iar opțiunile sunt scoped pe rol.)

it('FORMULAR: disciplina pe care N-O PREDĂ în clasa aleasă e respinsă (nu e în opțiuni)', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$this->class->id,
            'subject_id' => $this->foreignSubject->id,
            'assigned_on' => now()->toDateString(),
            'required_task' => 'X',
        ])
        ->call('create')
        ->assertHasFormErrors(['subject_id']);

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

it('FORMULAR: clasa fără alocare proprie e respinsă, chiar la aceeași treaptă (7 „2")', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$this->otherClass->id,
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'required_task' => 'X',
        ])
        ->call('create')
        ->assertHasFormErrors(['class_target']);

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

it('FORMULAR: profesorul NU poate alege „toată treapta" (grade:7 nu e în opțiunile lui)', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'grade:7',
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'required_task' => 'X',
        ])
        ->call('create')
        ->assertHasFormErrors(['class_target']);

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

// ─── Protecția pe SERVER — stratul 2: EnforcesHomeworkScope (apărare de profunzime) ───────
// Trait-ul e garda finală, independentă de opțiunile formularului: îl testăm direct, cu date
// „manipulate" care ar fi trecut de un formular greșit configurat.

/** @param array<string, mixed> $data */
function enforceHomeworkAs(array $data, bool $creating = true): array
{
    $enforcer = new class
    {
        use EnforcesHomeworkScope;

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function run(array $data, bool $creating): array
        {
            return $this->enforceHomeworkScope($data, $creating);
        }
    };

    return $enforcer->run($data, $creating);
}

it('TRAIT: disciplina străină e respinsă cu mesajul de scope', function () {
    try {
        enforceHomeworkAs([
            'class_target' => 'class:'.$this->class->id,
            'subject_id' => $this->foreignSubject->id,
            'topic' => 'X',
        ]);
        $this->fail('Trebuia respinsă.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('data.subject_id')
            ->and($e->errors()['data.subject_id'][0])->toBe(__('panel.validation.scope.not_your_class_subject'));
    }

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

it('TRAIT: „toată treapta" e refuzată profesorului cu mesaj dedicat', function () {
    try {
        enforceHomeworkAs([
            'class_target' => 'grade:7',
            'subject_id' => $this->subject->id,
            'topic' => 'X',
        ]);
        $this->fail('Trebuia respinsă.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('data.class_target')
            ->and($e->errors()['data.class_target'][0])->toBe(__('panel.validation.homework.whole_grade_admin_only'));
    }
});

it('TRAIT: o țintă inexistentă (class:{id fals}) e respinsă cu mesaj clar', function () {
    try {
        enforceHomeworkAs([
            'class_target' => 'class:999999',
            'subject_id' => $this->subject->id,
            'topic' => 'X',
        ]);
        $this->fail('Trebuia respinsă.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('data.class_target')
            ->and($e->errors()['data.class_target'][0])->toBe(__('panel.validation.homework.class_target_invalid'));
    }
});

it('TRAIT: la EDITARE autorul original NU se atinge, chiar dacă payload-ul îl injectează', function () {
    $data = enforceHomeworkAs([
        'class_target' => 'class:'.$this->class->id,
        'subject_id' => $this->subject->id,
        'topic' => 'X',
        'teacher_id' => 424242,
        'author_name' => 'Uzurpator',
    ], creating: false);

    expect($data)->not->toHaveKey('teacher_id')
        ->not->toHaveKey('author_name');
});

// ─── Administrația: toată treapta, cu secție NULL reală ──────────────────────────────────

it('administrația poate da temă pentru toată treapta, iar secția intră NULL (nu șir gol)', function () {
    actingAs(homeworkAdmin());

    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'grade:7',
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'topic' => 'Anunț pentru toată treapta',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $homework = HomeworkAssignment::query()->sole();

    // NULL strict — cabinetul, scoping-ul resursei și navigatorul caută whereNull('section').
    expect($homework->section)->toBeNull()
        ->and($homework->grade_level)->toBe(7)
        ->and($homework->teacher_id)->toBeNull()
        ->and($homework->author_name)->not->toBeNull()
        ->and($homework->subject_name)->toBe($this->subject->name);
});

// ─── Conținutul obligatoriu ──────────────────────────────────────────────────────────────

it('o temă fără subiect ȘI fără sarcină obligatorie e respinsă', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$this->class->id,
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'optional_task' => 'doar opțională',
        ])
        ->call('create')
        ->assertHasFormErrors(['topic', 'required_task']);

    expect(HomeworkAssignment::query()->count())->toBe(0);
});
