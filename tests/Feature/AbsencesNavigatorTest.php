<?php

/**
 * Navigatorul de catalog al paginii „Absențe" (același drill-down ca la Note, prin
 * HasCatalogNavigator): scoping pe rol, context aplicat pe tabel, chips, validarea id-urilor
 * din URL, absența pe zi întreagă (fără disciplină) și pre-completarea formularului.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Models\Absence;
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
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->ownClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ABS-A', 'section' => null]);
    $this->foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ABS-B', 'section' => null]);
    $this->subject = Subject::factory()->create();

    $this->ownStudent = Student::factory()->create();
    Enrollment::factory()->for($this->ownStudent)->for($this->ownClass)->for($this->year)->create();
    $this->foreignStudent = Student::factory()->create();
    Enrollment::factory()->for($this->foreignStudent)->for($this->foreignClass)->for($this->year)->create();
});

/** Profesor cu alocare pe (clasă, disciplină), opțional diriginte al unei clase. */
function absNavTeacher(SchoolClass $class, Subject $subject, ?SchoolClass $homeroom = null): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    if ($homeroom !== null) {
        $homeroom->update(['homeroom_teacher_id' => $teacher->id]);
    }

    return $user;
}

function absNavDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

function absNavAbsence(Student $student, SchoolClass $class, ?Subject $subject, Term $term, ?Teacher $teacher = null, string $on = '2025-10-10'): Absence
{
    return Absence::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject?->id,
        'term_id' => $term->id,
        'teacher_id' => $teacher?->id,
        'occurred_on' => $on,
        'is_motivated' => false,
    ]);
}

// ─── Bara temporală (2026-07-18): aceeași logică ca la Teme/Note, pe data absenței ────────

it('modul „zi" arată doar absențele zilei de referință; navigarea mută perioada', function () {
    actingAs(absNavDirector());

    $onDay = absNavAbsence($this->ownStudent, $this->ownClass, $this->subject, $this->term, on: '2025-10-10');
    $otherDay = absNavAbsence($this->ownStudent, $this->ownClass, $this->subject, $this->term, on: '2025-10-11');

    $component = Livewire::withQueryParams(['mod' => 'zi', 'ref' => '2025-10-10'])
        ->test(ListAbsences::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$onDay])
        ->assertCanNotSeeTableRecords([$otherDay]);

    // ▶ pe zi: referința devine 11 oct → celalaltă absență intră, prima iese.
    $component->call('shiftTimePeriod', 1)
        ->assertCanSeeTableRecords([$otherDay])
        ->assertCanNotSeeTableRecords([$onDay]);
});

// ─── Navigator scoped pe rol ─────────────────────────────────────────────────────────────

it('cardurile de clase ale profesorului conțin DOAR clasele lui (absențe)', function () {
    actingAs(absNavTeacher($this->ownClass, $this->subject));

    $cards = Livewire::test(ListAbsences::class)->instance()->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->ownClass->id]);
});

it('profesorul nu are dimensiunea „Profesori"; administrația da (absențe)', function () {
    actingAs(absNavTeacher($this->ownClass, $this->subject));
    expect(Livewire::test(ListAbsences::class)->instance()->catalogDimensions())->not->toHaveKey('profesori');

    actingAs(absNavDirector());
    expect(Livewire::test(ListAbsences::class)->instance()->catalogDimensions())->toHaveKey('profesori');
});

// ─── Contextul restrânge tabelul ─────────────────────────────────────────────────────────

it('deschiderea unei clase restrânge tabelul la absențele ei', function () {
    actingAs(absNavDirector());

    $own = absNavAbsence($this->ownStudent, $this->ownClass, $this->subject, $this->term);
    $foreign = absNavAbsence($this->foreignStudent, $this->foreignClass, $this->subject, $this->term);

    Livewire::test(ListAbsences::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('id de clasă din afara scope-ului, venit prin URL, nu deschide context (absențe)', function () {
    actingAs(absNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['clasa' => (string) $this->foreignClass->id])
        ->test(ListAbsences::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

it('absența pe ZI ÎNTREAGĂ (fără disciplină) apare în contextul clasei, sub „Toate"', function () {
    actingAs(absNavDirector());

    $wholeDay = absNavAbsence($this->ownStudent, $this->ownClass, null, $this->term);
    $onSubject = absNavAbsence($this->ownStudent, $this->ownClass, $this->subject, $this->term);

    $component = Livewire::test(ListAbsences::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$wholeDay, $onSubject]);

    // Chip pe disciplină → absența fără disciplină iese din listă (chip-ul e o restrângere).
    $component->call('setCatalogChip', $this->subject->id)
        ->assertCanSeeTableRecords([$onSubject])
        ->assertCanNotSeeTableRecords([$wholeDay]);
});

it('dirigintele primește chips pentru toate disciplinele clasei lui (absențe)', function () {
    $user = absNavTeacher($this->ownClass, $this->subject, homeroom: $this->ownClass);

    $otherSubject = Subject::factory()->create();
    absNavTeacher($this->ownClass, $otherSubject); // colegul predă altă disciplină în clasă

    actingAs($user);

    $chips = Livewire::test(ListAbsences::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->instance()
        ->catalogChips();

    expect(collect($chips)->pluck('id')->all())
        ->toContain($this->subject->id)
        ->toContain($otherSubject->id);
});

// ─── Pre-completarea formularului din context ────────────────────────────────────────────

it('formularul de consemnare se pre-completează din context (clasă + disciplină predată în clasă)', function () {
    actingAs(absNavTeacher($this->ownClass, $this->subject));

    Livewire::withQueryParams(['clasa' => (string) $this->ownClass->id, 'disciplina' => (string) $this->subject->id])
        ->test(CreateAbsence::class)
        ->assertFormSet([
            'school_class_id' => $this->ownClass->id,
            'subject_id' => $this->subject->id,
        ]);
});

it('o disciplină care NU se predă în clasa din context nu se pre-completează', function () {
    $orphanSubject = Subject::factory()->create(); // fără alocare în ABS-A

    actingAs(absNavDirector());

    Livewire::withQueryParams(['clasa' => (string) $this->ownClass->id, 'disciplina' => (string) $orphanSubject->id])
        ->test(CreateAbsence::class)
        ->assertFormSet([
            'school_class_id' => $this->ownClass->id,
            'subject_id' => null,
        ]);
});
