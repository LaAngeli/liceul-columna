<?php

/**
 * F3 — SEPARAREA Profesor/Diriginte pe CONTEXT (doc pct. 5, decizia beneficiarului 30.07.2026).
 *
 * Persoana-test: profesor+diriginte (multi-rol). Predă disciplina lui la clasa A (unde e și
 * diriginte) și la clasa B; clasa C îi e străină. Contractul:
 *   - context PROFESOR: vede clasele PREDATE (A+B) — inclusiv A, dar „exclusiv cu drepturi de
 *     profesor": fără motivări, fără puterile de dirigenție;
 *   - context DIRIGINTE: vede EXCLUSIV clasa de dirigenție (A) — toată clasa, motivările,
 *     calendarul de clasă; notarea rămâne act de profesor (comuti ca să notezi);
 *   - MONO-rol: perimetrul istoric fuzionat, neatins (contractul F0).
 */

use App\Enums\UserRole;
use App\Filament\Pages\ClassRegister;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\ActiveRole;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year)->create([
        'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(2),
        'ends_on' => Carbon::today()->addMonths(2),
    ]);

    $this->subject = Subject::factory()->create(['grading_type' => 'n']);

    // Persoana multi-rol: Profesor + Diriginte.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $user->assignRole(UserRole::Diriginte->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->user = $user->fresh();

    // A = dirigenția LUI, predă și acolo; B = doar predă; C = străină.
    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'V', 'section' => 'A', 'grade_level' => 5, 'homeroom_teacher_id' => $this->teacher->id]);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => 'VI', 'section' => 'B', 'grade_level' => 6]);
    $this->classC = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => 'C', 'grade_level' => 7]);

    foreach ([$this->classA, $this->classB] as $class) {
        TeachingAssignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    // Câte un elev cu o notă + o absență în fiecare clasă (A: pe ALTĂ disciplină decât a lui,
    // ca să se vadă diferența diriginte-vede-tot vs profesor-doar-disciplina-lui).
    $this->otherSubject = Subject::factory()->create(['grading_type' => 'n']);

    foreach (['A' => $this->classA, 'B' => $this->classB, 'C' => $this->classC] as $key => $class) {
        $student = Student::factory()->create(['last_name' => 'Elev'.$key]);
        Enrollment::factory()->for($student)->for($class)->for($this->year)->create(['left_on' => null]);
        $this->{'student'.$key} = $student;

        Grade::query()->create([
            'student_id' => $student->id,
            'subject_id' => $key === 'A' ? $this->otherSubject->id : $this->subject->id,
            'school_class_id' => $class->id,
            'term_id' => $this->term->id,
            'teacher_id' => null,
            'graded_on' => Carbon::today()->subDays(5),
            'evaluation_type' => 'curenta',
            'value' => 8,
        ]);
    }
});

function activateContext(User $user, UserRole $role): void
{
    session()->put(ActiveRole::SESSION_KEY, $role->value);
}

it('în context PROFESOR vede clasele predate — dirigenția fără puteri de diriginte', function () {
    actingAs($this->user);
    activateContext($this->user, UserRole::Profesor);

    expect($this->user->contextClassIds())
        ->toEqualCanonicalizing([$this->classA->id, $this->classB->id])
        // Puterile de dirigenție sunt STINSE: fără motivări, fără calendar de clasă.
        ->and($this->user->contextHomeroomClassIds())->toBe([])
        ->and(AbsenceMotivationResource::canAccess())->toBeFalse()
        ->and($this->user->canMotivateAbsencesFor($this->classA->id))->toBeFalse();

    // Notele vizibile: DOAR perechile (clasă, disciplina LUI) — nota de la altă disciplină
    // din clasa de dirigenție nu se vede (drepturi de profesor, doc pct. 5).
    $visible = Grade::applyStaffVisibility(Grade::query(), $this->user->fresh())->pluck('student_id');

    expect($visible)->toContain($this->studentB->id)
        ->and($visible)->not->toContain($this->studentA->id)
        ->and($visible)->not->toContain($this->studentC->id);
});

it('în context DIRIGINTE vede EXCLUSIV clasa lui — toată clasa, cu motivări', function () {
    actingAs($this->user);
    activateContext($this->user, UserRole::Diriginte);

    expect($this->user->contextClassIds())->toBe([$this->classA->id])
        ->and($this->user->contextHomeroomClassIds())->toBe([$this->classA->id])
        ->and(AbsenceMotivationResource::canAccess())->toBeTrue()
        ->and($this->user->canMotivateAbsencesFor($this->classA->id))->toBeTrue();

    // Notele vizibile: TOATĂ clasa A (orice disciplină) — dar nimic din B (predată) sau C.
    $visible = Grade::applyStaffVisibility(Grade::query(), $this->user->fresh())->pluck('student_id');

    expect($visible)->toContain($this->studentA->id)
        ->and($visible)->not->toContain($this->studentB->id)
        ->and($visible)->not->toContain($this->studentC->id);
});

it('navigatorul de Note arată cardurile contextului activ', function () {
    actingAs($this->user);

    activateContext($this->user, UserRole::Profesor);
    $cards = Livewire::test(ListGrades::class)->instance()->catalogEntityCards();
    expect(array_column($cards, 'id'))->toEqualCanonicalizing([$this->classA->id, $this->classB->id]);

    activateContext($this->user, UserRole::Diriginte);
    $cards = Livewire::test(ListGrades::class)->instance()->catalogEntityCards();
    expect(array_column($cards, 'id'))->toBe([$this->classA->id]);
});

it('borderoul în context Diriginte: doar clasa lui, fără input de note, cu absențe', function () {
    actingAs($this->user);
    activateContext($this->user, UserRole::Diriginte);

    $page = Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ClassRegister::class)->instance();

    expect($page->classOptions()->pluck('id')->all())->toBe([$this->classA->id])
        // Notarea e act de PROFESOR — în context Diriginte comuti ca să notezi.
        ->and($page->canEnterGrades())->toBeFalse()
        ->and($page->canRecordAbsences())->toBeTrue();

    // În context Profesor, aceeași pagină notează normal.
    activateContext($this->user, UserRole::Profesor);

    $page = Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ClassRegister::class)->instance();

    expect($page->classOptions()->pluck('id')->all())
        ->toEqualCanonicalizing([$this->classA->id, $this->classB->id])
        ->and($page->canEnterGrades())->toBeTrue();
});

it('salvarea din borderou rămâne permisă pe server în ambele contexte (gărzile = reuniune)', function () {
    actingAs($this->user);

    // Context Profesor: nota intră la clasa de dirigenție (predă acolo) — drepturi de profesor.
    activateContext($this->user, UserRole::Profesor);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id, 'disciplina' => (string) $this->subject->id])
        ->test(ClassRegister::class)
        ->set('entries', [(string) $this->studentA->id => ['value' => '9']])
        ->call('saveEntries')
        ->assertHasNoErrors();

    expect(Grade::query()->where('student_id', $this->studentA->id)->where('subject_id', $this->subject->id)->count())->toBe(1);

    // Context Diriginte: absența intră pe orice disciplină a clasei lui.
    activateContext($this->user, UserRole::Diriginte);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id, 'disciplina' => (string) $this->otherSubject->id])
        ->test(ClassRegister::class)
        ->set('entries', [(string) $this->studentA->id => ['absence' => ClassRegister::ABSENCE_MARKED]])
        ->call('saveEntries')
        ->assertHasNoErrors();

    expect(Absence::query()->where('student_id', $this->studentA->id)->count())->toBe(1);
});

it('mono-rol păstrează perimetrul fuzionat — contractul F0 pe scoping', function () {
    // Aceeași topologie, dar cont cu UN singur rol (diriginte) — comportamentul istoric.
    $mono = User::factory()->create();
    $mono->assignRole(UserRole::Diriginte->value);
    $monoTeacher = Teacher::factory()->create(['user_id' => $mono->id]);

    $this->classB->update(['homeroom_teacher_id' => null]);

    // QUERY BUILDER, nu model: pe calea aplicației, observerul de alocări i-ar acorda membria
    // „Profesor" (cumul-ul, 2026-07-31) și contul ar deveni MULTI-rol. Aici reproducem exact
    // starea MOȘTENITĂ din import — mono-rol, cu perimetru fuzionat.
    TeachingAssignment::query()->insert([
        'teacher_id' => $monoTeacher->id,
        'school_class_id' => $this->classB->id,
        'subject_id' => $this->subject->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    SchoolClass::query()->whereKey($this->classC->id)->update(['homeroom_teacher_id' => $monoTeacher->id]);

    $mono = $mono->fresh();
    actingAs($mono);

    // Fuzionat: predată (B) + dirigenție (C), ca înainte de F3.
    expect($mono->teachingContext())->toBeNull()
        ->and($mono->contextClassIds())->toEqualCanonicalizing([$this->classB->id, $this->classC->id])
        ->and($mono->contextHomeroomClassIds())->toBe([$this->classC->id]);
});
