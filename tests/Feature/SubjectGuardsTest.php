<?php

/**
 * Cluster nomenclatoare, lotul D — gărzile pe Discipline:
 *  - modul de notare (numeric ↔ calificativ) e BLOCAT cât timp există note de tip incompatibil
 *    (invariantul „notă SAU calificativ" trăia doar în UI-ul formularului de notă);
 *  - schimbarea între tipuri compatibile rămâne permisă.
 *
 * NB: numele disciplinei NU se forțează unic — datele legacy au legitim 10 nume duplicate
 * (aceeași disciplină pe trepte diferite, ex. „Matematică" ×2); gruparea din cabinet pe nume
 * e o chestiune separată (clusterul cabinet).
 */

use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();
});

it('blochează comutarea numeric→calificativ pe o disciplină cu note NUMERICE existente', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 8,
    ]);

    Livewire::test(EditSubject::class, ['record' => $subject->id])
        ->fillForm(['grading_type' => GradingType::Calificativ->value])
        ->call('save')
        ->assertHasFormErrors(['grading_type']);

    expect($subject->fresh()->grading_type)->toBe(GradingType::Numeric);
});

it('blochează comutarea calificativ→numeric când există CALIFICATIVE în catalog', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Calificativ]);
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id,
        'value' => null, 'calificativ' => 'FB',
    ]);

    Livewire::test(EditSubject::class, ['record' => $subject->id])
        ->fillForm(['grading_type' => GradingType::Numeric->value])
        ->call('save')
        ->assertHasFormErrors(['grading_type']);
});

it('permite schimbarea modului de notare pe o disciplină FĂRĂ note (sau între tipuri compatibile)', function () {
    // Fără note → orice comutare e liberă (disciplina abia configurată).
    $fresh = Subject::factory()->create(['grading_type' => GradingType::Numeric]);

    Livewire::test(EditSubject::class, ['record' => $fresh->id])
        ->fillForm(['grading_type' => GradingType::Calificativ->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fresh->fresh()->grading_type)->toBe(GradingType::Calificativ);

    // Între tipuri NE-numerice (calificativ → calificativ+descriptiv), cu calificative existente → permis.
    $qualitative = Subject::factory()->create(['grading_type' => GradingType::Calificativ]);
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id, 'subject_id' => $qualitative->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id,
        'value' => null, 'calificativ' => 'B',
    ]);

    Livewire::test(EditSubject::class, ['record' => $qualitative->id])
        ->fillForm(['grading_type' => GradingType::CalificativDescriptiv->value])
        ->call('save')
        ->assertHasNoFormErrors();
});
