<?php

/**
 * Fluxul STANDARDIZAT de creare/editare a disciplinelor (cerința beneficiarului, 2026-07-21):
 * treptele se aleg din selectoare I–XII (interval imposibil de inversat în UI, dublat pe server),
 * poziția în foaia matricolă e unică și contiguă (inserarea împinge restul; scrierea trece DOAR
 * prin placeInReportOrder), numele se normalizează, garda de model prinde orice cale de scriere,
 * iar îngustarea intervalului peste istoricul existent (alocări/note) e blocată.
 */

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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
});

it('creează disciplina din selectoare și primește automat următoarea poziție din foaia matricolă', function () {
    Subject::factory()->create(['report_order' => 1, 'name' => 'Limba română']);

    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Educația digitală',
            'grading_type' => GradingType::Numeric->value,
            'min_grade' => 5,
            'max_grade' => 9,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subject = Subject::query()->where('name', 'Educația digitală')->firstOrFail();

    expect($subject->min_grade)->toBe(5)
        ->and($subject->max_grade)->toBe(9)
        // Auto-completare: poziția implicită = următoarea liberă (după „Limba română" pe 1).
        ->and($subject->report_order)->toBe(2);
});

it('respinge pe SERVER intervalul inversat, chiar dacă UI-ul nu-l permite (POST forjat)', function () {
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Disciplină forjată',
            'grading_type' => GradingType::Numeric->value,
            'min_grade' => 9,
            'max_grade' => 5,
        ])
        ->call('create')
        ->assertHasFormErrors(['max_grade']);

    expect(Subject::query()->where('name', 'Disciplină forjată')->exists())->toBeFalse();
});

it('interzice suprapunerea intervalelor pentru discipline omonime, dar permite intervale disjuncte', function () {
    Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 1, 'max_grade' => 4, 'grading_type' => GradingType::Calificativ]);

    // Suprapunere (3 ≤ 4) → respins.
    Livewire::test(CreateSubject::class)
        ->fillForm(['name' => 'Matematică', 'grading_type' => GradingType::Numeric->value, 'min_grade' => 3, 'max_grade' => 12])
        ->call('create')
        ->assertHasFormErrors(['max_grade']);

    // Disjunct (5–12) → legitim (același nume, alt ciclu, alt mod de notare).
    Livewire::test(CreateSubject::class)
        ->fillForm(['name' => 'Matematică', 'grading_type' => GradingType::Numeric->value, 'min_grade' => 5, 'max_grade' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Subject::query()->where('name', 'Matematică')->count())->toBe(2);
});

it('poziția aleasă inserează în foaia matricolă și împinge restul — pozițiile rămân unice și contigue', function () {
    $romana = Subject::factory()->create(['name' => 'Limba română', 'report_order' => 1]);
    $mate = Subject::factory()->create(['name' => 'Matematică', 'report_order' => 2]);
    $istorie = Subject::factory()->create(['name' => 'Istoria', 'report_order' => 3]);

    // Disciplină nouă pe poziția 2 → Matematică și Istoria se împing pe 3 și 4.
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Limba engleză',
            'grading_type' => GradingType::Numeric->value,
            'min_grade' => 1,
            'max_grade' => 12,
            'report_order' => '2',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $orderByName = Subject::query()->whereNotNull('report_order')->orderBy('report_order')->pluck('name')->all();
    expect($orderByName)->toBe(['Limba română', 'Limba engleză', 'Matematică', 'Istoria']);

    // Mutarea unei discipline existente (Istoria 4 → 1) renumerotează contiguu.
    Livewire::test(EditSubject::class, ['record' => $istorie->getKey()])
        ->fillForm(['report_order' => '1'])
        ->call('save')
        ->assertHasNoFormErrors();

    $orderByName = Subject::query()->whereNotNull('report_order')->orderBy('report_order')->pluck('name')->all();
    expect($orderByName)->toBe(['Istoria', 'Limba română', 'Limba engleză', 'Matematică'])
        ->and(Subject::query()->whereNotNull('report_order')->orderBy('report_order')->pluck('report_order')->all())
        ->toBe([1, 2, 3, 4]);
});

it('îngustarea intervalului peste istoricul existent este blocată cu treptele afectate', function () {
    $subject = Subject::factory()->create(['name' => 'Fizica', 'min_grade' => 6, 'max_grade' => 12]);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 11]);
    TeachingAssignment::factory()->create([
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    // 6–10 ar lăsa alocarea clasei a XI-a în afara intervalului → blocat.
    Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
        ->fillForm(['max_grade' => 10])
        ->call('save')
        ->assertHasFormErrors(['max_grade']);

    // Lărgirea rămâne permisă.
    Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
        ->fillForm(['min_grade' => 5, 'max_grade' => 12])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('garda de MODEL prinde orice cale de scriere: interval invalid sau treaptă inexistentă', function () {
    expect(fn () => Subject::factory()->create(['min_grade' => 9, 'max_grade' => 5]))
        ->toThrow(ValidationException::class);

    expect(fn () => Subject::factory()->create(['min_grade' => 0, 'max_grade' => 13]))
        ->toThrow(ValidationException::class);
});

it('numele se normalizează la salvare (spații multiple, margini)', function () {
    $subject = Subject::factory()->create(['name' => '  Educația   pentru   societate  ']);

    expect($subject->name)->toBe('Educația pentru societate');
});

it('abrevierea se propune din denumire (inițiale la mai multe cuvinte, prefix la unul singur)', function () {
    expect(SubjectForm::suggestAbbreviation('Educația fizică și sportul'))->toBe('EFS')
        ->and(SubjectForm::suggestAbbreviation('Matematica'))->toBe('MATE')
        ->and(SubjectForm::suggestAbbreviation('Chimia'))->toBe('CHIM');
});

it('selectoarele de trepte vin din structura școlii (I–XII), etichetate cu ciclul', function () {
    $options = SchoolCycle::gradeLevelOptions();

    expect(array_keys($options))->toBe(range(1, 12))
        ->and($options[1])->toContain('I')->toContain('Primar')
        ->and($options[12])->toContain('XII')->toContain('Liceu');

    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.forms.subject.grade_option', $locale))->toBeTrue("Lipsește grade_option [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.subject.section_transcript_hint', $locale))->toBeTrue("Lipsește section_transcript_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.subject.grade_span_narrow_blocked', $locale))->toBeTrue("Lipsește narrow_blocked [{$locale}]");
    }
});

it('modul de notare rămâne blocat cât timp există note incompatibile', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $student = Student::factory()->create();

    Grade::factory()->create([
        'subject_id' => $subject->id,
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'value' => 9,
        'calificativ' => null,
    ]);

    Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
        ->fillForm(['grading_type' => GradingType::Calificativ->value])
        ->call('save')
        ->assertHasFormErrors(['grading_type']);
});
