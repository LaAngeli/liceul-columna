<?php

/**
 * Cluster nomenclatoare, lotul B: gărzile de validare care înainte se terminau în erori SQL 500
 * (index unic lovit direct) sau în date incoerente:
 *  - înmatriculare duplicat peste un rând ARHIVAT (indexul unic nu are deleted_at în cheie);
 *  - înmatriculare cu clasa altui an școlar decât cel ales;
 *  - clasă duplicat (an, treaptă, literă) — activă sau arhivată;
 *  - semestre suprapuse din ANI diferiți (Term::forDate devenea ambiguu);
 *  - invariantul „un singur semestru curent" în fața soft-delete/restore.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\Terms\Pages\CreateTerm;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
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
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->student = Student::factory()->create();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
});

// ─── Înmatriculări ───────────────────────────────────────────────────────────────────────

it('re-înmatricularea peste o înmatriculare ARHIVATĂ dă mesaj de restaurare, nu eroare SQL', function () {
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create()->delete();

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_year_id' => __('panel.validation.enrollment.archived_duplicate')]);

    expect(Enrollment::query()->count())->toBe(0); // doar cea arhivată există (exclusă de scope)
});

it('duplicatul ACTIV de înmatriculare păstrează mesajul clasic', function () {
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_year_id' => __('panel.validation.enrollment.duplicate')]);
});

it('respinge înmatricularea cu clasa ALTUI an școlar decât cel ales', function () {
    $otherYear = AcademicYear::factory()->create();
    $otherYearClass = SchoolClass::factory()->for($otherYear)->create();

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $otherYearClass->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['school_class_id' => __('panel.validation.enrollment.class_year_mismatch')]);

    expect(Enrollment::query()->count())->toBe(0);
});

// ─── Clase ───────────────────────────────────────────────────────────────────────────────

it('clasa duplicat (an, treaptă, literă) e respinsă cu mesaj clar, nu cu eroare SQL', function () {
    $existing = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 7,
            'name' => 'VII',
            'section' => 'A',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['section' => __('panel.validation.school_class.duplicate')]);

    // Duplicatul ARHIVAT (indexul unic îl vede în continuare) → îndrumare spre restaurare.
    $existing->delete();

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'grade_level' => 7,
            'name' => 'VII',
            'section' => 'A',
            'homeroom_teacher_id' => Teacher::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['section' => __('panel.validation.school_class.archived_duplicate')]);
});

// ─── Semestre ────────────────────────────────────────────────────────────────────────────

it('respinge un semestru suprapus cu semestrul ALTUI an școlar', function () {
    // Anul existent are semestru pe ian–iun 2100. Un al doilea an, cu interval care îl acoperă,
    // primește un semestru feb–mai 2100 → în interiorul propriului an, dar suprapus cu primul.
    Term::factory()->for($this->year)->create([
        'number' => 2,
        'starts_on' => ((int) $this->year->starts_on->format('Y') + 1).'-01-10',
        'ends_on' => ((int) $this->year->starts_on->format('Y') + 1).'-06-20',
    ]);
    $overlapYearStart = (int) $this->year->starts_on->format('Y');
    $otherYear = AcademicYear::factory()->create([
        'starts_on' => $overlapYearStart.'-10-01',
        'ends_on' => ($overlapYearStart + 1).'-07-31',
    ]);

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $otherYear->id,
            'number' => 1,
            'starts_on' => ($overlapYearStart + 1).'-02-01',
            'ends_on' => ($overlapYearStart + 1).'-05-31',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on' => __('panel.validation.term.overlap')]);
});

it('marcarea unui semestru drept curent stinge și un fost-curent ARHIVAT', function () {
    // Starea „fost-curent arhivat" se construiește prin query builder: garda de MODEL
    // (2026-07-21) nu mai lasă semestrul CURENT să fie șters — starea e moștenire.
    $trashedCurrent = Term::factory()->for($this->year)->create(['number' => 1, 'is_current' => false]);
    $trashedCurrent->delete();
    Term::withTrashed()->whereKey($trashedCurrent->id)->update(['is_current' => true]);

    $newCurrent = Term::factory()->for($this->year)->create(['number' => 2, 'is_current' => false]);
    $newCurrent->update(['is_current' => true]);

    // Fostul curent, deși arhivat, a fost stins → restaurarea lui nu mai aduce un al doilea curent.
    expect(Term::withTrashed()->find($trashedCurrent->id)->is_current)->toBeFalse()
        ->and(Term::query()->where('is_current', true)->count())->toBe(1);
});

it('restaurarea unui semestru fost-curent NU produce doi curenți — cel activ câștigă', function () {
    // Fost-curent arhivat CU flag-ul încă aprins (stare moștenită, dinaintea gărzilor): se
    // construiește ne-curent (garda de model nu lasă curentul șters), apoi flag prin query builder.
    $formerCurrent = Term::factory()->for($this->year)->create(['number' => 1, 'is_current' => false]);
    $formerCurrent->delete();
    Term::withTrashed()->whereKey($formerCurrent->id)->update(['is_current' => true]);

    $activeCurrent = Term::factory()->for($this->year)->create(['number' => 2, 'is_current' => true]);

    Term::withTrashed()->find($formerCurrent->id)->restore();

    expect(Term::query()->where('is_current', true)->count())->toBe(1)
        ->and($activeCurrent->fresh()->is_current)->toBeTrue()
        ->and($formerCurrent->fresh()->is_current)->toBeFalse();
});
