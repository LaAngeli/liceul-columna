<?php

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Filament\Resources\SummativeDesignations\Pages\CreateSummativeDesignation;
use App\Filament\Resources\SummativeDesignations\Pages\ListSummativeDesignations;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SummativeDesignation;
use App\Models\Term;
use App\Models\User;
use App\Support\Summatives;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * @return array{class: SchoolClass, term: Term, student: Student, subject: Subject}
 */
function designationSetup(int $gradeLevel = 7): array
{
    $year = AcademicYear::factory()->create();

    return [
        'class' => SchoolClass::factory()->for($year)->create(['grade_level' => $gradeLevel]),
        'term' => Term::factory()->for($year)->create(),
        'student' => Student::factory()->create(),
        'subject' => Subject::factory()->create(),
    ];
}

/**
 * @param  array{class: SchoolClass, term: Term, student: Student, subject: Subject}  $ctx
 */
function makeGrade(array $ctx, EvaluationType $type): Grade
{
    return Grade::factory()->make([
        'student_id' => $ctx['student']->id,
        'subject_id' => $ctx['subject']->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => 8,
        'evaluation_type' => $type,
    ]);
}

it('eticheta sumativei se derivă din ciclu: ESS la gimnaziu, teză la liceu', function () {
    app()->setLocale('ro');

    $gimnaziu = SummativeDesignation::factory()->create([
        'school_class_id' => SchoolClass::factory()->create(['grade_level' => 8])->id,
    ]);
    $liceu = SummativeDesignation::factory()->create([
        'school_class_id' => SchoolClass::factory()->create(['grade_level' => 11])->id,
    ]);

    expect($gimnaziu->summativeLabel())->toBe('ESS (sumativă semestrială)')
        ->and($liceu->summativeLabel())->toBe('Teză');
});

it('permite sumativă pe o disciplină designată', function () {
    $ctx = designationSetup();
    SummativeDesignation::factory()->create([
        'subject_id' => $ctx['subject']->id,
        'school_class_id' => $ctx['class']->id,
    ]);

    makeGrade($ctx, EvaluationType::Teza)->save();

    expect(Grade::query()->where('evaluation_type', EvaluationType::Teza->value)->count())->toBe(1);
});

it('blochează sumativă pe o disciplină nedesignată dintr-o clasă configurată', function () {
    $ctx = designationSetup();
    // Clasa e configurată (are o designare, dar la ALTĂ disciplină).
    SummativeDesignation::factory()->create([
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $ctx['class']->id,
    ]);

    makeGrade($ctx, EvaluationType::Teza)->save();
})->throws(ValidationException::class);

it('permite sumativă pe o clasă neconfigurată (fail-open pentru date legacy)', function () {
    $ctx = designationSetup();
    // Nicio designare pentru clasă → garda e inactivă.
    makeGrade($ctx, EvaluationType::Teza)->save();

    expect(Grade::query()->where('evaluation_type', EvaluationType::Teza->value)->count())->toBe(1);
});

it('nu blochează notele curente — doar sumativele sunt gardate', function () {
    $ctx = designationSetup();
    SummativeDesignation::factory()->create([
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $ctx['class']->id,
    ]);

    makeGrade($ctx, EvaluationType::Curenta)->save();
    makeGrade($ctx, EvaluationType::Esi)->save();

    expect(Grade::query()->whereIn('evaluation_type', [EvaluationType::Curenta->value, EvaluationType::Esi->value])->count())->toBe(2);
});

it('semnalează disciplinele designate fără notă sumativă (teze lipsă)', function () {
    $ctx = designationSetup();
    $withTeza = Subject::factory()->create();
    $withoutTeza = Subject::factory()->create();

    SummativeDesignation::factory()->create(['subject_id' => $withTeza->id, 'school_class_id' => $ctx['class']->id]);
    SummativeDesignation::factory()->create(['subject_id' => $withoutTeza->id, 'school_class_id' => $ctx['class']->id]);

    Grade::factory()->create([
        'student_id' => $ctx['student']->id,
        'subject_id' => $withTeza->id,
        'school_class_id' => $ctx['class']->id,
        'term_id' => $ctx['term']->id,
        'value' => 9,
        'evaluation_type' => EvaluationType::Teza,
    ]);

    $missing = Summatives::missingForStudentTerm($ctx['student']->id, $ctx['class']->id, $ctx['term']->id);

    expect($missing)->toHaveCount(1)
        ->and((int) $missing->first()->subject_id)->toBe($withoutTeza->id);
});

it('nu semnalează nimic pentru o clasă fără designări', function () {
    $ctx = designationSetup();

    expect(Summatives::missingForStudentTerm($ctx['student']->id, $ctx['class']->id, $ctx['term']->id))->toHaveCount(0);
});

it('resursa Filament de designare se randează pentru management (listă + creare)', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);

    Livewire::test(ListSummativeDesignations::class)->assertOk();
    Livewire::test(CreateSummativeDesignation::class)->assertOk();
});
