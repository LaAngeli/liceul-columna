<?php

/**
 * Fluxul STANDARDIZAT de creare/editare a disciplinelor (2026-07-21; set discret din 07.08.2026):
 * treptele se MARCHEAZĂ una câte una (CheckboxList I–XII — setul poate avea goluri, omonimele
 * stau pe seturi disjuncte), poziția în foaia matricolă e unică și contiguă (inserarea împinge
 * restul; scrierea trece DOAR prin placeInReportOrder), numele se normalizează, garda de model
 * prinde orice cale de scriere, iar debifarea unei trepte cu istoric (alocări/note) e blocată.
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
            'grade_levels' => range(5, 9),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subject = Subject::query()->where('name', 'Educația digitală')->firstOrFail();

    expect($subject->grade_levels)->toBe(range(5, 9))
        // Auto-completare: poziția implicită = următoarea liberă (după „Limba română" pe 1).
        ->and($subject->report_order)->toBe(2);
});

it('acceptă un set NECONTIGUU de trepte — exact ce intervalul nu putea exprima', function () {
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Disciplină cu pauză',
            'grading_type' => GradingType::Numeric->value,
            'grade_levels' => [5, 6, 9, 12],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subject = Subject::query()->where('name', 'Disciplină cu pauză')->firstOrFail();

    expect($subject->grade_levels)->toBe([5, 6, 9, 12])
        ->and($subject->coversGrade(9))->toBeTrue()
        ->and($subject->coversGrade(7))->toBeFalse()
        // Eticheta compresează rulajele și ARATĂ golurile.
        ->and($subject->gradeLevelsLabel())->toBe('V–VI, IX, XII');
});

it('respinge pe SERVER treptele din afara structurii, chiar dacă UI-ul nu le oferă (POST forjat)', function () {
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Disciplină forjată',
            'grading_type' => GradingType::Numeric->value,
            'grade_levels' => [13],
        ])
        ->call('create')
        ->assertHasFormErrors(['grade_levels.0']);

    // Fără nicio treaptă marcată → obligatoriu.
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Disciplină forjată',
            'grading_type' => GradingType::Numeric->value,
            'grade_levels' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['grade_levels']);

    expect(Subject::query()->where('name', 'Disciplină forjată')->exists())->toBeFalse();
});

it('interzice treptele COMUNE la discipline omonime, dar permite seturi disjuncte', function () {
    Subject::factory()->create(['name' => 'Matematică', 'grade_levels' => range(1, 4), 'grading_type' => GradingType::Calificativ]);

    // Treapta 3 e deja a fișei de primar → respins, pe câmpul de trepte.
    Livewire::test(CreateSubject::class)
        ->fillForm(['name' => 'Matematică', 'grading_type' => GradingType::Numeric->value, 'grade_levels' => range(3, 12)])
        ->call('create')
        ->assertHasFormErrors(['grade_levels']);

    // Disjunct (5–12) → legitim (același nume, alt ciclu, alt mod de notare).
    Livewire::test(CreateSubject::class)
        ->fillForm(['name' => 'Matematică', 'grading_type' => GradingType::Numeric->value, 'grade_levels' => range(5, 12)])
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
            'grade_levels' => range(1, 12),
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

it('debifarea unei trepte cu istoric este blocată; treptele libere se pot scoate, adăugarea e liberă', function () {
    $subject = Subject::factory()->create(['name' => 'Fizica', 'grade_levels' => range(6, 12)]);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 11]);
    TeachingAssignment::factory()->create([
        'subject_id' => $subject->id,
        'school_class_id' => $class->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    // Debifarea treptei a XI-a (are alocare) → blocat, chiar dacă restul rămâne.
    Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
        ->fillForm(['grade_levels' => range(6, 10)])
        ->call('save')
        ->assertHasFormErrors(['grade_levels']);

    // Debifarea unei trepte FĂRĂ istoric (a VI-a) + adăugarea alteia → permise.
    Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
        ->fillForm(['grade_levels' => [5, 7, 8, 9, 10, 11, 12]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subject->refresh()->grade_levels)->toBe([5, 7, 8, 9, 10, 11, 12]);
});

it('garda de MODEL prinde orice cale de scriere: treaptă inexistentă, iar setul se normalizează', function () {
    expect(fn () => Subject::factory()->create(['grade_levels' => [13]]))
        ->toThrow(ValidationException::class);

    expect(fn () => Subject::factory()->create(['grade_levels' => [0]]))
        ->toThrow(ValidationException::class);

    // Dezordine + dubluri + numere-string → listă sortată de întregi unici.
    $subject = Subject::factory()->create(['grade_levels' => ['9', 5, 9, '7']]);

    expect($subject->grade_levels)->toBe([5, 7, 9]);

    // Setul golit devine NULL (nomenclator incomplet), nu „nu se predă nicăieri".
    $subject = Subject::factory()->create(['grade_levels' => []]);

    expect($subject->grade_levels)->toBeNull()
        ->and($subject->coversGrade(3))->toBeTrue();
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
            ->and(Lang::hasForLocale('panel.forms.subject.created_assignments_body', $locale))->toBeTrue("Lipsește created_assignments_body [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.subject.grade_levels_overlap', $locale))->toBeTrue("Lipsește grade_levels_overlap [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.subject.grade_levels_remove_blocked', $locale))->toBeTrue("Lipsește grade_levels_remove_blocked [{$locale}]");
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
