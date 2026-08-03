<?php

/**
 * „Deschide anul nou" (2026-08-03): structura anului precedent urcă o treaptă — clasele (cu
 * secția și dirigintele) plus alocările care mai au sens la treapta nouă. Perechea structurală a
 * promovării elevilor: aceea mută OAMENII, aceasta pregătește locurile în care ajung.
 */

use App\Actions\AcademicYears\OpenAcademicYear;
use App\Enums\UserRole;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->source = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30',
    ]);
    $this->target = AcademicYear::factory()->create([
        'name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
    ]);
});

/** Un configurator autentificat (AO) — deschiderea anului îi aparține. */
function yearOpener(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);
    actingAs($user);

    return $user;
}

it('urcă fiecare clasă o treaptă, păstrând secția și dirigintele', function () {
    $teacher = Teacher::factory()->create();
    SchoolClass::factory()->for($this->source)->create([
        'grade_level' => 5, 'name' => 'V', 'section' => 'A', 'homeroom_teacher_id' => $teacher->id,
    ]);
    SchoolClass::factory()->for($this->source)->create([
        'grade_level' => 8, 'name' => 'VIII', 'section' => 'B',
    ]);

    $result = app(OpenAcademicYear::class)->handle($this->target, $this->source);

    expect($result['classes'])->toBe(2)
        ->and($result['blocked'])->toBeNull();

    $created = SchoolClass::query()->where('academic_year_id', $this->target->id)->orderBy('grade_level')->get();

    expect($created->pluck('grade_level')->all())->toBe([6, 9])
        // Numele se generează din treaptă (garda de model) — cifra romană a clasei NOI.
        ->and($created->pluck('name')->all())->toBe(['VI', 'IX'])
        ->and($created->pluck('section')->all())->toBe(['A', 'B'])
        ->and((int) $created->first()->homeroom_teacher_id)->toBe($teacher->id);
});

it('lasă anul-sursă neatins', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);

    app(OpenAcademicYear::class)->handle($this->target, $this->source);

    expect(SchoolClass::query()->where('academic_year_id', $this->source->id)->count())->toBe(1);
});

it('NU promovează clasa a XII-a: absolvirea nu e o clasă a XIII-a', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 12, 'section' => 'R']);

    $plan = app(OpenAcademicYear::class)->plan($this->target, $this->source);
    $result = app(OpenAcademicYear::class)->handle($this->target, $this->source);

    expect($plan['graduating'])->toHaveCount(1)
        ->and($plan['promoted'])->toBe([])
        ->and($result['classes'])->toBe(0)
        ->and(SchoolClass::query()->where('academic_year_id', $this->target->id)->count())->toBe(0);
});

it('preia alocările doar unde disciplina se predă la treapta nouă', function () {
    $class = SchoolClass::factory()->for($this->source)->create(['grade_level' => 4, 'section' => 'A']);
    $teacher = Teacher::factory()->create();

    // Disciplină de PRIMAR (I–IV): nu urcă în gimnaziu.
    $primary = Subject::factory()->create(['min_grade' => 1, 'max_grade' => 4]);
    // Disciplină care acoperă treapta nouă.
    $continues = Subject::factory()->create(['min_grade' => 1, 'max_grade' => 9]);

    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $primary->id,
    ]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $continues->id,
    ]);

    $result = app(OpenAcademicYear::class)->handle($this->target, $this->source);

    $new = SchoolClass::query()->where('academic_year_id', $this->target->id)->firstOrFail();

    expect($result['assignments'])->toBe(1)
        ->and($result['dropped'])->toBe(1)
        ->and(TeachingAssignment::query()->where('school_class_id', $new->id)->pluck('subject_id')->all())
        ->toBe([$continues->id]);
});

it('poate crea DOAR clasele, fără alocări, când operatorul alege așa', function () {
    $class = SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);
    TeachingAssignment::factory()->create([
        'teacher_id' => Teacher::factory()->create()->id,
        'school_class_id' => $class->id,
        'subject_id' => Subject::factory()->create(['min_grade' => 1, 'max_grade' => 12])->id,
    ]);

    $result = app(OpenAcademicYear::class)->handle($this->target, $this->source, withAssignments: false);

    expect($result['classes'])->toBe(1)
        ->and($result['assignments'])->toBe(0)
        ->and(TeachingAssignment::query()->whereIn(
            'school_class_id',
            SchoolClass::query()->where('academic_year_id', $this->target->id)->pluck('id'),
        )->count())->toBe(0);
});

it('e idempotentă: clasele existente în anul-țintă se sar, nu se dublează', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 6, 'section' => 'B']);

    $first = app(OpenAcademicYear::class)->handle($this->target, $this->source);
    $second = app(OpenAcademicYear::class)->handle($this->target, $this->source);

    expect($first['classes'])->toBe(2)
        ->and($second['classes'])->toBe(0)
        ->and($second['existing'])->toBe(2)
        ->and(SchoolClass::query()->where('academic_year_id', $this->target->id)->count())->toBe(2);
});

it('vede și clasa ARHIVATĂ din anul-țintă (indexul unic o vede) și n-o recreează', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);
    SchoolClass::factory()->for($this->target)->create(['grade_level' => 6, 'section' => 'A'])->delete();

    $plan = app(OpenAcademicYear::class)->plan($this->target, $this->source);
    $result = app(OpenAcademicYear::class)->handle($this->target, $this->source);

    expect($result['classes'])->toBe(0)
        ->and($plan['existing'])->toHaveCount(1)
        ->and($plan['existing'][0]['archived'])->toBeTrue();
});

it('refuză un an ÎNCHIS și un sursă care nu e înaintea țintei', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);

    $closed = AcademicYear::factory()->create([
        'name' => '2028–2029', 'starts_on' => '2028-09-01', 'ends_on' => '2029-06-30', 'closed_at' => now(),
    ]);

    $action = app(OpenAcademicYear::class);

    expect($action->handle($closed, $this->source)['blocked'])->toBe('closed')
        ->and($action->handle($this->source, $this->target)['blocked'])->toBe('not_after')
        ->and($action->handle($this->target, $this->target)['blocked'])->toBe('same_year')
        ->and(SchoolClass::query()->where('academic_year_id', $closed->id)->count())->toBe(0);
});

it('oferă drept sursă doar anii ANTERIORI care au clase', function () {
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);
    // An anterior FĂRĂ clase + an ulterior CU clase: niciunul nu e sursă validă.
    AcademicYear::factory()->create(['name' => '2024–2025', 'starts_on' => '2024-09-01', 'ends_on' => '2025-06-30']);
    $later = AcademicYear::factory()->create(['name' => '2030–2031', 'starts_on' => '2030-09-01', 'ends_on' => '2031-06-30']);
    SchoolClass::factory()->for($later)->create(['grade_level' => 7, 'section' => 'C']);

    $sources = app(OpenAcademicYear::class)->sourceYearsFor($this->target);

    expect(array_keys($sources))->toBe([$this->source->id]);
});

// ── Pagina (hub-ul anilor) ──────────────────────────────────────────────────────────────────

it('butonul apare pe anul care se pregătește și lipsește pe cel cu registru pornit', function () {
    yearOpener();

    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'section' => 'A']);

    $cards = collect(Livewire::test(ListAcademicYears::class)->instance()->yearCards())->keyBy('id');

    expect($cards[$this->target->id]['can_open'])->toBeTrue()
        // Anul sursă e primul din cronologie: n-are de unde prelua.
        ->and($cards[$this->source->id]['can_open'])->toBeFalse();
});

it('rulează din pagină și raportează ce s-a creat', function () {
    yearOpener();

    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'A']);
    SchoolClass::factory()->for($this->source)->create(['grade_level' => 12, 'name' => 'XII', 'section' => 'R']);

    // Butonul din card montează modalul pe anul cerut.
    Livewire::test(ListAcademicYears::class)
        ->call('startYearOpening', $this->target->id)
        ->assertActionMounted('openYear');

    $component = Livewire::test(ListAcademicYears::class)->set('openingYearId', $this->target->id);

    // Previzualizarea E decizia: spune și ce NU urcă.
    $summary = (string) $component->instance()->openYearSummary($this->source->id, true);

    expect($summary)->toContain('VI A')
        ->and($summary)->toContain(__('panel.actions.open_year.graduating', ['classes' => 'XII R']));

    $component->callAction('openYear', ['source_year_id' => $this->source->id, 'with_assignments' => true]);

    expect(SchoolClass::query()->where('academic_year_id', $this->target->id)->pluck('name')->all())->toBe(['VI']);
});

it('prim-vicedirectorul vede hub-ul, dar nu poate deschide un an', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::PrimVicedirector->value);
    actingAs($user);

    SchoolClass::factory()->for($this->source)->create(['grade_level' => 5, 'name' => 'V', 'section' => 'A']);

    $component = Livewire::test(ListAcademicYears::class)->assertActionHidden('openYear');

    expect(collect($component->instance()->yearCards())->firstWhere('id', $this->target->id)['can_open'])->toBeFalse();

    // Dublura pe SERVER: chiar apelată direct, ruta de execuție nu scrie nimic.
    $component->set('openingYearId', $this->target->id)->call('runYearOpening', $this->source->id, true);

    expect(SchoolClass::query()->where('academic_year_id', $this->target->id)->count())->toBe(0);
});
