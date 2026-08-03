<?php

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Filament\Resources\SummativeDesignations\Pages\CreateSummativeDesignation;
use App\Filament\Resources\SummativeDesignations\Pages\ListSummativeDesignations;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
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

    Livewire::test(ListSummativeDesignations::class)
        ->assertOk()
        ->assertActionVisible('create');
    Livewire::test(CreateSummativeDesignation::class)->assertOk();
});

it('administratorul operațional consultă designările, dar nu vede butonul de adăugare', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $operational = User::factory()->create();
    $operational->assignRole(UserRole::AdministratorOperational->value);
    $this->actingAs($operational);

    // Sumativa e decizie pedagogică (intră 50% în media semestrială) → o scrie doar cine
    // administrează catalogul. Butonul NU trebuie doar refuzat la click, ci să lipsească.
    Livewire::test(ListSummativeDesignations::class)
        ->assertOk()
        ->assertActionHidden('create');

    $this->get(SummativeDesignationResource::getUrl('create'))->assertForbidden();
});

// ─── Aterizarea pe CLASE (restructurarea 2026-08-04) ─────────────────────────────────────

it('aterizarea grupează clasele pe cicluri și arată disciplinele ca etichete', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);

    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['is_current' => true]);

    // Primarul NU apare: acolo nu există notă sumativă semestrială.
    SchoolClass::factory()->for($year)->create(['grade_level' => 3, 'name' => 'III', 'section' => 'A']);
    $gimnaziu = SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'A']);
    $liceu = SchoolClass::factory()->for($year)->create(['grade_level' => 11, 'name' => 'XI', 'section' => 'R']);

    $subject = Subject::factory()->create(['name' => 'Fizică', 'min_grade' => 6, 'max_grade' => 12]);
    SummativeDesignation::query()->create(['school_class_id' => $liceu->id, 'subject_id' => $subject->id]);

    $page = Livewire::test(ListSummativeDesignations::class)->instance();
    $groups = collect($page->classGroups());

    expect($groups->pluck('label')->all())->toBe([
        __('panel.catalog_nav.cycles.gimnaziu'),
        __('panel.catalog_nav.cycles.liceu'),
    ]);

    $cards = $groups->flatMap(fn (array $group): array => $group['cards'])->keyBy('title');

    expect($cards->keys()->all())->toBe(['VII A', 'XI R'])
        // Clasa fără desemnări e marcată ca atare — nu e „goală", e negardată.
        ->and($cards['VII A']['configured'])->toBeFalse()
        ->and($cards['XI R']['configured'])->toBeTrue()
        ->and($cards['XI R']['subjects'])->toBe(['Fizică'])
        // Tipul se derivă din ciclu, deci nu mai e o coloană repetată pe rânduri.
        ->and($cards['VII A']['type'])->not->toBe($cards['XI R']['type']);

    // Acoperirea: semnalul care lipsea cu totul.
    $coverage = $page->coverage();

    expect($coverage['total'])->toBe(2)
        ->and($coverage['configured'])->toBe(1)
        ->and($coverage['missing'])->toBe(1)
        ->and($coverage['missing_labels'])->toBe(['VII A'])
        ->and($gimnaziu->fresh())->not->toBeNull();
});

it('contextul unei clase restrânge tabelul, iar o clasă străină nu deschide context', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);

    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['is_current' => true]);

    $classA = SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'A']);
    $classB = SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'B']);
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    $designationA = SummativeDesignation::query()->create(['school_class_id' => $classA->id, 'subject_id' => $subject->id]);
    $designationB = SummativeDesignation::query()->create(['school_class_id' => $classB->id, 'subject_id' => $subject->id]);

    Livewire::test(ListSummativeDesignations::class)
        ->call('openClass', $classA->id)
        ->assertCanSeeTableRecords([$designationA])
        ->assertCanNotSeeTableRecords([$designationB]);

    // Clasa primară nu e designabilă → id-ul ei nu deschide context.
    $primar = SchoolClass::factory()->for($year)->create(['grade_level' => 3, 'name' => 'III', 'section' => 'C']);

    $page = Livewire::test(ListSummativeDesignations::class)->call('openClass', $primar->id)->instance();

    expect($page->hasClassContext())->toBeFalse();
});

it('desemnarea în masă creează perechile lipsă, sare peste existente și refuză treptele nepotrivite', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);

    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['is_current' => true]);

    $vii = SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'A']);
    $xi = SchoolClass::factory()->for($year)->create(['grade_level' => 11, 'name' => 'XI', 'section' => 'R']);

    $peste = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
    $doarLiceu = Subject::factory()->create(['name' => 'Astronomie', 'min_grade' => 11, 'max_grade' => 12]);

    // O pereche există deja: trebuie sărită, nu dublată.
    SummativeDesignation::query()->create(['school_class_id' => $vii->id, 'subject_id' => $peste->id]);

    Livewire::test(ListSummativeDesignations::class)
        ->callAction('bulkDesignate', [
            'class_ids' => [$vii->id, $xi->id],
            'subject_ids' => [$peste->id, $doarLiceu->id],
            'order_reference' => 'Ordin 5/2026',
        ]);

    $created = SummativeDesignation::query()->get()
        ->map(fn (SummativeDesignation $row): string => $row->school_class_id.':'.$row->subject_id)
        ->sort()
        ->values()
        ->all();

    // VII×Matematică exista; VII×Astronomie NU se creează (nu se predă la treapta a VII-a).
    expect($created)->toBe(collect([
        $vii->id.':'.$peste->id,
        $xi->id.':'.$peste->id,
        $xi->id.':'.$doarLiceu->id,
    ])->sort()->values()->all())
        ->and(SummativeDesignation::query()->where('school_class_id', $xi->id)->first()->order_reference)
        ->toBe('Ordin 5/2026');
});

it('desemnarea în masă e închisă cui nu administrează catalogul', function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['is_current' => true]);
    SchoolClass::factory()->for($year)->create(['grade_level' => 7]);

    // Administratorul operațional configurează școala, dar sumativa e decizie pedagogică:
    // aceeași regulă ca la butonul de adăugare simplă.
    $operational = User::factory()->create();
    $operational->assignRole(UserRole::AdministratorOperational->value);
    $this->actingAs($operational);

    Livewire::test(ListSummativeDesignations::class)
        ->assertOk()
        ->assertActionHidden('bulkDesignate');
});
