<?php

/**
 * Ecranul UNIC de trecere în anul nou (cerința beneficiarului, 2026-08-03: „prea mulți pași,
 * împrăștiați — se pot omite"). Verifică exact ce compune orchestratorul: anul, semestrele,
 * structura care urcă o treaptă, absolvirea promoției și mutarea elevilor — într-o operațiune.
 */

use App\Actions\AcademicYears\StartSchoolYear;
use App\Enums\DepartureReason;
use App\Enums\UserRole;
use App\Filament\Pages\SchoolYearTransition;
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

    $this->operator = User::factory()->create();
    $this->operator->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->operator);

    $this->source = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30',
    ]);
    Term::factory()->for($this->source)->create([
        'number' => 1, 'name' => 'Semestrul I', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => true,
    ]);

    // O clasă care urcă (V A) și una terminală (XII R) — cele două destine ale unei promoții.
    $this->classV = SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'A']);
    $this->classXII = SchoolClass::factory()->for($this->source)->create(['grade_level' => 12, 'name' => 'XII', 'section' => 'R']);

    $subject = Subject::factory()->create(['min_grade' => 1, 'max_grade' => 12]);
    TeachingAssignment::factory()->create([
        'teacher_id' => Teacher::factory()->create()->id,
        'school_class_id' => $this->classV->id,
        'subject_id' => $subject->id,
    ]);

    $this->elevV = Student::factory()->create();
    Enrollment::factory()->for($this->elevV)->for($this->classV)->for($this->source)->create();

    $this->absolvent = Student::factory()->create();
    Enrollment::factory()->for($this->absolvent)->for($this->classXII)->for($this->source)->create();
});

/** Datele minime ale unei treceri complete, cu an nou creat pe loc. */
function transitionInput(AcademicYear $source, array $overrides = []): array
{
    return [
        'source_year_id' => $source->getKey(),
        'target_year_id' => null,
        'year' => ['name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30'],
        'terms' => [
            ['number' => 1, 'name' => 'Semestrul I', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31'],
            ['number' => 2, 'name' => 'Semestrul II', 'starts_on' => '2027-01-15', 'ends_on' => '2027-05-31'],
        ],
        'with_assignments' => true,
        'graduate' => true,
        'promote' => true,
        ...$overrides,
    ];
}

it('face toată trecerea dintr-o singură operațiune', function () {
    $result = app(StartSchoolYear::class)->handle(transitionInput($this->source));

    $target = AcademicYear::query()->where('name', '2026–2027')->sole();

    expect($result['blocked'])->toBeNull()
        ->and($result['year']?->getKey())->toBe($target->getKey())
        ->and($result['terms'])->toBe(2)
        // V A urcă în VI A; XII R nu urcă (absolvire).
        ->and($result['classes'])->toBe(1)
        ->and($result['assignments'])->toBe(1)
        ->and($result['graduates'])->toBe(1)
        ->and($result['students'])->toBe(1);

    $newClass = SchoolClass::query()->where('academic_year_id', $target->id)->sole();

    expect($newClass->grade_level)->toBe(6)
        ->and($newClass->name)->toBe('VI')
        ->and($newClass->section)->toBe('A')
        // Elevul e în clasa nouă, iar rândul vechi rămâne ca istoric.
        ->and(Enrollment::query()->where('student_id', $this->elevV->id)->count())->toBe(2)
        ->and(Enrollment::query()->where('student_id', $this->elevV->id)->where('academic_year_id', $target->id)->sole()->school_class_id)
        ->toBe($newClass->id);

    // Absolventul a ieșit din registru cu motivul potrivit.
    $absolvire = Enrollment::query()->where('student_id', $this->absolvent->id)->sole();
    expect($absolvire->left_on)->not->toBeNull()
        ->and($absolvire->departure_reason)->toBe(DepartureReason::Absolvire);

    // Semestrele anului nou există, iar „curent" nu se decide de aici.
    expect(Term::query()->where('academic_year_id', $target->id)->count())->toBe(2)
        ->and(Term::query()->where('academic_year_id', $target->id)->where('is_current', true)->exists())->toBeFalse();
});

it('e idempotentă: reluarea nu dublează nimic', function () {
    $action = app(StartSchoolYear::class);
    $action->handle(transitionInput($this->source));

    $target = AcademicYear::query()->where('name', '2026–2027')->sole();

    // A doua rulare, de data asta pe anul existent.
    $second = $action->handle(transitionInput($this->source, ['target_year_id' => $target->getKey()]));

    expect($second['terms'])->toBe(0)
        ->and($second['classes'])->toBe(0)
        ->and($second['existing'])->toBe(1)
        ->and($second['graduates'])->toBe(0)
        ->and($second['students'])->toBe(0)
        ->and(SchoolClass::query()->where('academic_year_id', $target->id)->count())->toBe(1)
        ->and(Term::query()->where('academic_year_id', $target->id)->count())->toBe(2)
        ->and(AcademicYear::query()->where('name', '2026–2027')->count())->toBe(1);
});

it('comutatoarele chiar decid: fără alocări, fără absolvire, fără elevi', function () {
    $result = app(StartSchoolYear::class)->handle(transitionInput($this->source, [
        'with_assignments' => false,
        'graduate' => false,
        'promote' => false,
    ]));

    $target = AcademicYear::query()->where('name', '2026–2027')->sole();

    expect($result['classes'])->toBe(1)
        ->and($result['assignments'])->toBe(0)
        ->and($result['graduates'])->toBe(0)
        ->and($result['students'])->toBe(0)
        ->and(TeachingAssignment::query()->whereIn(
            'school_class_id',
            SchoolClass::query()->where('academic_year_id', $target->id)->pluck('id'),
        )->count())->toBe(0)
        // Absolventul a rămas activ, fiindcă așa s-a cerut.
        ->and(Enrollment::query()->where('student_id', $this->absolvent->id)->sole()->left_on)->toBeNull();
});

it('previzualizarea spune aceleași cifre ca execuția, fără să scrie nimic', function () {
    $action = app(StartSchoolYear::class);
    $input = transitionInput($this->source);

    $plan = $action->plan($input);

    expect($plan['classes'])->toBe(1)
        ->and($plan['assignments'])->toBe(1)
        ->and($plan['graduates'])->toBe(1)
        ->and($plan['students'])->toBe(1)
        ->and($plan['terms'])->toBe(2)
        ->and($plan['unmapped'])->toBe(['XII R'])
        // Nimic scris în urma previzualizării.
        ->and(AcademicYear::query()->count())->toBe(1);

    $result = $action->handle($input);

    expect($result['classes'])->toBe($plan['classes'])
        ->and($result['assignments'])->toBe($plan['assignments'])
        ->and($result['graduates'])->toBe($plan['graduates'])
        ->and($result['students'])->toBe($plan['students']);
});

it('propune numele anului următor și semestrele lui, după tiparul precedentului', function () {
    $action = app(StartSchoolYear::class);

    $terms = $action->suggestTerms($this->source, '2026-09-01');

    expect($action->suggestName($this->source))->toBe('2026–2027')
        ->and($terms)->toHaveCount(1)
        ->and($terms[0]['name'])->toBe('Semestrul I')
        // Aceleași granițe, mutate cu un an.
        ->and($terms[0]['starts_on'])->toBe('2026-09-01')
        ->and($terms[0]['ends_on'])->toBe('2026-12-31');
});

// ─── Ecranul ─────────────────────────────────────────────────────────────────────────────

it('ecranul rulează toată trecerea și raportează ce s-a făcut', function () {
    $component = Livewire::test(SchoolYearTransition::class);

    // Formularul vine pre-completat: anul-sursă și numele anului nou sunt deja propuse.
    // (Starea formularului poartă id-ul ca string — de-aia comparăm după cast.)
    expect((int) $component->instance()->data['source_year_id'])->toBe($this->source->getKey())
        ->and($component->instance()->data['year']['name'])->toBe('2026–2027');

    $component->call('start')->assertHasNoErrors();

    $target = AcademicYear::query()->where('name', '2026–2027')->sole();

    expect($component->instance()->report['classes'])->toBe(1)
        ->and($component->instance()->report['graduates'])->toBe(1)
        ->and($component->instance()->report['students'])->toBe(1)
        ->and(SchoolClass::query()->where('academic_year_id', $target->id)->count())->toBe(1);
});

it('ecranul e închis celor fără drept de configurare', function () {
    expect(SchoolYearTransition::canAccess())->toBeTrue();

    foreach ([UserRole::Profesor, UserRole::Diriginte, UserRole::PrimVicedirector] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        actingAs($user);

        expect(SchoolYearTransition::canAccess())->toBeFalse("Rolul {$role->value} nu trebuie să deschidă anul");
    }
});
