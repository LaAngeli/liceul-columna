<?php

/**
 * Înmatriculări — registrul claselor (2026-07-16): navigator ani → carduri de clase (activi/
 * plecați) → registrul clasei (tabel scoped), adăugare PRE-COMPLETATĂ din context, elevii deja
 * înmatriculați în anul ales excluși din selecție (stratul 1; regula de pe an rămâne stratul 2 —
 * NomenclatureValidationGuardsTest), plecarea marcată direct din rând, anul stocat derivat din
 * clasă pe server.
 */

use App\Enums\DepartureReason;
use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Datele explicite: factory-ul dă un `starts_on` aleator, iar aserțiile pe ORDINE ar fi
    // depins de el (nu de eticheta anului).
    $this->oldYear = AcademicYear::factory()->create([
        'name' => '2019–2020', 'starts_on' => '2019-09-01', 'ends_on' => '2020-06-30',
    ]);
    $this->year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30',
    ]);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->oldClass = SchoolClass::factory()->for($this->oldYear)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'V']);

    $this->ana = Student::factory()->create(['last_name' => 'EN-Anghel', 'first_name' => 'Ana']);
    $this->activeEnrollment = Enrollment::factory()->for($this->ana)->for($this->classA)->for($this->year)
        ->create(['enrolled_on' => '2025-09-01', 'left_on' => null]);

    $this->ion = Student::factory()->create(['last_name' => 'EN-Bostan', 'first_name' => 'Ion']);
    $this->departedEnrollment = Enrollment::factory()->for($this->ion)->for($this->classA)->for($this->year)
        ->create(['enrolled_on' => '2025-09-01', 'left_on' => '2025-12-01']);

    $this->oldEnrollment = Enrollment::factory()->for(Student::factory()->create())->for($this->oldClass)->for($this->oldYear)->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

it('registrul se navighează: ani → carduri de clase (activi/plecați) → înmatriculările clasei', function () {
    $component = Livewire::test(ListEnrollments::class);
    $page = $component->instance();

    // Pastilele anilor (cronologic crescător), badge = elevii aflați EFECTIV în școală: anul
    // curent are două rânduri, dar unul e plecat, deci badge-ul lui e 1 (restructurare 2026-08-02
    // — totalul rândurilor nu spunea cât cântărește anul).
    expect(collect($page->yearPills())->pluck('id')->all())->toBe([$this->oldYear->id, $this->year->id])
        ->and(collect($page->yearPills())->pluck('count')->all())->toBe([1, 1])
        ->and($page->activeYearId())->toBe($this->year->id);

    // Cardurile sunt GRUPATE pe cicluri (52 de carduri plate nu se parcurgeau cu ochiul).
    $groups = $page->classGroups();
    expect($groups)->toHaveCount(1)
        ->and($groups[0]['cycle'])->toBe('gimnaziu')
        ->and(collect($groups[0]['cards'])->pluck('id')->all())->toBe([$this->classA->id])
        ->and($groups[0]['cards'][0]['active'])->toBe(1)
        ->and($groups[0]['cards'][0]['departed'])->toBe(1);

    // Registrul clasei = doar înmatriculările ei.
    $component->call('openClass', $this->classA->id)
        ->assertCanSeeTableRecords([$this->activeEnrollment, $this->departedEnrollment])
        ->assertCanNotSeeTableRecords([$this->oldEnrollment]);
});

it('o clasă inexistentă venită prin URL nu deschide registrul', function () {
    $component = Livewire::withQueryParams(['clasa' => '999999'])->test(ListEnrollments::class);

    expect($component->instance()->activeClass())->toBeNull();
});

it('adăugarea vine pre-completată din context: clasa din registru, elevul din lista de neînmatriculați', function () {
    // Formularul nu mai are câmp „an școlar" (restructurare 2026-08-03): anul e o consecință a
    // clasei — se afișa doar ca să fie apoi suprascris la salvare.
    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(CreateEnrollment::class)
        ->assertFormSet(['school_class_id' => $this->classA->id]);

    // Din „Neînmatriculați": elevul sosește deja bifat în selecția multiplă.
    $nou = Student::factory()->create(['last_name' => 'EN-Prefill', 'first_name' => 'Ana']);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id, 'elev' => (string) $nou->id])
        ->test(CreateEnrollment::class)
        ->assertFormSet([
            'school_class_id' => $this->classA->id,
            'students' => [$nou->id],
        ]);

    // Context inexistent → câmpurile rămân goale, fără eroare.
    Livewire::withQueryParams(['clasa' => '999999', 'elev' => '999999'])
        ->test(CreateEnrollment::class)
        ->assertFormSet(['school_class_id' => null, 'students' => []]);
});

it('elevul deja înmatriculat în anul ales nu mai e oferit la selecție', function () {
    // Ana are deja înmatriculare în anul curent → nu e printre opțiuni → bariera `in` a Select-ului.
    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'school_class_id' => $this->classA->id,
            'students' => [$this->ana->id],
            'enrolled_on' => '2025-09-15',
        ])
        ->call('create')
        ->assertHasFormErrors(['students']);

    expect(Enrollment::query()->count())->toBe(3);
});

it('înmatricularea nouă cere data, acceptă MAI MULȚI elevi și stochează anul CLASEI', function () {
    $unu = Student::factory()->create(['last_name' => 'EN-Nou', 'first_name' => 'Radu']);
    $doi = Student::factory()->create(['last_name' => 'EN-Nou', 'first_name' => 'Vlad']);

    // Fără dată → obligatorie la creare.
    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'school_class_id' => $this->classA->id,
            'students' => [$unu->id],
            'enrolled_on' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['enrolled_on']);

    // O singură trecere prin formular = două rânduri de registru (înmatriculare în masă).
    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'school_class_id' => $this->classA->id,
            'students' => [$unu->id, $doi->id],
            'enrolled_on' => '2025-09-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $enrollments = Enrollment::query()->whereIn('student_id', [$unu->id, $doi->id])->get();

    expect($enrollments)->toHaveCount(2)
        // Anul vine din CLASĂ, nu dintr-un câmp — nu mai există unul de contrazis.
        ->and($enrollments->pluck('academic_year_id')->unique()->all())->toBe([$this->classA->academic_year_id])
        ->and($enrollments->pluck('school_class_id')->unique()->all())->toBe([$this->classA->id]);
});

it('plecarea se marchează direct din registru, doar pe rândurile active', function () {
    $component = Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class);

    // Pe rândul deja plecat, acțiunea nu există.
    $component->assertTableActionHidden('departure', $this->departedEnrollment);

    $component->callTableAction('departure', $this->activeEnrollment, [
        'left_on' => '2026-01-15',
        'departure_reason' => DepartureReason::Transfer->value,
    ])->assertHasNoTableActionErrors();

    // Motivul e OBLIGATORIU: data singură nu spune dacă elevul a absolvit sau a fost exmatriculat.
    expect($this->activeEnrollment->fresh()->left_on?->toDateString())->toBe('2026-01-15')
        ->and($this->activeEnrollment->fresh()->departure_reason)->toBe(DepartureReason::Transfer);
});

it('plecarea nu poate precede înmatricularea', function () {
    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->callTableAction('departure', $this->activeEnrollment, [
            'left_on' => '2025-08-01',
            'departure_reason' => DepartureReason::Retragere->value,
        ])
        ->assertHasTableActionErrors(['left_on']);

    expect($this->activeEnrollment->fresh()->left_on)->toBeNull();
});

it('prim-vicedirectorul consultă registrul, dar nu operează în el', function () {
    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->assertCanSeeTableRecords([$this->activeEnrollment])
        ->assertActionHidden('create')
        ->assertTableActionHidden('departure', $this->activeEnrollment)
        ->assertTableActionHidden('edit', $this->activeEnrollment);
});

// ─── Maturizarea registrului (2026-07-21): transfer, gărzi de ștergere, neînmatriculați ──

it('transferul mută elevul în altă clasă din același an, lasă urmă în audit și nu atinge notele vechi', function () {
    config(['audit.console' => true]);

    $classB = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'B']);
    $term = Term::query()->where('is_current', true)->firstOrFail();

    // Notă consemnată în clasa VECHE — snapshot istoric care NU se rescrie la transfer.
    $grade = Grade::factory()->create([
        'student_id' => $this->ana->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $this->classA->id,
        'term_id' => $term->id,
        'value' => 9,
    ]);

    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->callTableAction('transfer', $this->activeEnrollment, ['school_class_id' => $classB->id])
        ->assertNotified();

    expect($this->activeEnrollment->fresh()->school_class_id)->toBe($classB->id)
        // Anul rămâne același; nota veche rămâne pe clasa veche.
        ->and($this->activeEnrollment->fresh()->academic_year_id)->toBe($this->year->id)
        ->and($grade->fresh()->school_class_id)->toBe($this->classA->id)
        // Transferul e reconstruibil din jurnal: vechi→nou pe school_class_id.
        ->and(DB::table('audits')
            ->where('auditable_type', Enrollment::class)
            ->where('auditable_id', $this->activeEnrollment->id)
            ->where('event', 'updated')
            ->exists())->toBeTrue();
});

it('transferul refuză o clasă din ALT an școlar (POST meșterit) și pe elevul plecat nu apare deloc', function () {
    Livewire::withQueryParams(['clasa' => (string) $this->classA->id])
        ->test(ListEnrollments::class)
        ->assertTableActionHidden('transfer', $this->departedEnrollment)
        ->callTableAction('transfer', $this->activeEnrollment, ['school_class_id' => $this->oldClass->id]);

    // Ținta din alt an e respinsă de centura de server — nimic nu s-a schimbat.
    expect($this->activeEnrollment->fresh()->school_class_id)->toBe($this->classA->id);
});

it('rândul de registru cu istoric academic nu se șterge (policy + model); cel fără istoric, da', function () {
    $term = Term::query()->where('is_current', true)->firstOrFail();

    Grade::factory()->create([
        'student_id' => $this->ana->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $this->classA->id,
        'term_id' => $term->id,
    ]);

    expect($this->activeEnrollment->fresh()->hasAcademicHistory())->toBeTrue()
        ->and($this->director->can('delete', $this->activeEnrollment))->toBeFalse()
        ->and(fn () => $this->activeEnrollment->delete())
        ->toThrow(ValidationException::class);

    // Ion (plecat, fără note/absențe) rămâne curățabil — rând creat din greșeală.
    expect($this->director->can('delete', $this->departedEnrollment))->toBeTrue();
    $this->departedEnrollment->delete();
    expect($this->departedEnrollment->fresh()->trashed())->toBeTrue();
});

it('elevii fără NICIO înmatriculare în anul activ apar în lista de lucru; cei cu înmatriculare arhivată — în semnalul dedicat', function () {
    $orfan = Student::factory()->create(['last_name' => 'EN-Zugrav', 'first_name' => 'Radu']);

    $cuArhivata = Student::factory()->create(['last_name' => 'EN-Vulpe', 'first_name' => 'Dan']);
    Enrollment::factory()->for($cuArhivata)->for($this->classA)->for($this->year)
        ->create(['enrolled_on' => '2025-09-01'])
        ->delete();

    $page = Livewire::test(ListEnrollments::class)->instance();
    $unassigned = $page->unassigned();
    $names = collect($unassigned['students'])->pluck('name')->all();

    expect($names)->toContain('EN-Zugrav Radu')
        // Înmatriculat activ / cu rând arhivat → NU în lista „de înmatriculat".
        ->not->toContain('EN-Anghel Ana')
        ->not->toContain('EN-Vulpe Dan');

    $signals = collect($page->integrity());

    // Neînmatriculații NU mai sunt semnal de avertizare: au bara de progres a anului + cardul cu
    // listă (un al treilea loc care spunea același lucru era zgomot). Rândul ARHIVAT rămâne semnal
    // — „restaurați, nu recreați" e o îndrumare, nu o numărătoare.
    expect($signals->firstWhere('level', 'warning'))->toBeNull()
        ->and($signals->firstWhere('level', 'info'))->not->toBeNull()
        ->and($page->yearProgress()['enrolled'])->toBeLessThan($page->yearProgress()['total']);
});

/**
 * REGRESIE (raport beneficiar, 04.08.2026): „Promovare din anul precedent" nu făcea nimic la click.
 *
 * Cauza nu era în acțiune — ea se monta corect pe server — ci în șablon: `x-filament-panels::page`
 * lasă randarea modalelor în seama TABELULUI, iar tabelul acestei pagini trăiește DOAR în ramura
 * „clasă activă". Butonul apare însă exclusiv pe aterizarea pe an, unde tabel nu există: acțiunea se
 * monta, dar modalul ei nu avea unde să se randeze.
 *
 * Testul apără containerul, nu butonul: orice acțiune de antet adăugată aici depinde de el.
 */
it('aterizarea pe an randează containerul de modale — altfel acțiunile din antet nu au unde se deschide', function () {
    $component = Livewire::test(ListEnrollments::class);

    // Suntem pe aterizare (fără clasă), deci fără tabel: exact starea în care lipsea containerul.
    expect($component->instance()->activeClass())->toBeNull();

    $component->assertActionVisible('promote')
        // Containerul se randează chiar și fără acțiune montată — `fi-modal` apare abia la
        // deschidere, deci aserțiunea stă pe partiala care GĂZDUIEȘTE modalele.
        ->assertSeeHtml('action-modals');
});
