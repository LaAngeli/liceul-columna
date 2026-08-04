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
use App\Support\TermOptions;
use Illuminate\Support\Carbon;
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

    // forma=lista: în context de clasă vederea implicită e HARTA (04.08.2026); testul verifică TABELUL.
    $component = Livewire::withQueryParams(['mod' => 'zi', 'ref' => '2025-10-10', 'forma' => 'lista'])
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

    // forma=lista: vederea implicită în context de clasă e harta; aici se verifică TABELUL.
    Livewire::withQueryParams(['forma' => 'lista', 'mod' => 'toate'])->test(ListAbsences::class)
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

    // forma=lista: vederea implicită în context de clasă e harta; aici se verifică TABELUL.
    $component = Livewire::withQueryParams(['forma' => 'lista', 'mod' => 'toate'])->test(ListAbsences::class)
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

it('dimensiunea „Perioade" arată doar semestrele anului activ', function () {
    /**
     * Cerința beneficiarului (04.08.2026): semestrele anilor încheiați umpleau meniul cu carduri
     * „fără înregistrări încă" — o listă care crește cu fiecare an, în care semestrul de lucru se
     * pierde. Arhiva rămâne accesibilă acolo unde îi e locul (foaie matricolă, fișa elevului).
     */
    $vechi = AcademicYear::factory()->create(['name' => '2019–2020', 'starts_on' => '2019-09-01', 'ends_on' => '2020-06-30']);
    Term::factory()->for($vechi)->create(['number' => 1, 'name' => 'Semestrul I', 'starts_on' => '2019-09-01', 'ends_on' => '2019-12-31']);
    Term::factory()->for($vechi)->create(['number' => 2, 'name' => 'Semestrul II', 'starts_on' => '2020-01-15', 'ends_on' => '2020-06-30']);

    $alDoilea = Term::factory()->for($this->year)->create([
        'number' => 2, 'name' => 'Semestrul II', 'starts_on' => '2026-01-15', 'ends_on' => '2026-06-30',
    ]);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director->fresh());

    $cards = Livewire::test(ListAbsences::class)
        ->call('setCatalogDimension', 'perioade')
        ->instance()
        ->catalogEntityCards();

    // Doar cele două semestre ale anului activ, în ordine cronologică.
    expect(array_column($cards, 'id'))->toBe([(int) $this->term->id, (int) $alDoilea->id])
        ->and(array_unique(array_column($cards, 'subtitle')))->toBe([$this->year->name]);
});

it('filtrul „Semestrul" oferă anul activ în catalog și toți anii, cu anul în etichetă, pe fișa elevului', function () {
    $vechi = AcademicYear::factory()->create(['name' => '2019–2020', 'starts_on' => '2019-09-01', 'ends_on' => '2020-06-30']);
    $semVechi = Term::factory()->for($vechi)->create([
        'number' => 1, 'name' => 'Semestrul I', 'starts_on' => '2019-09-01', 'ends_on' => '2019-12-31',
    ]);

    // În catalog: doar semestrele anului activ, etichete scurte (nu există ambiguitate).
    expect(array_keys(TermOptions::current()))->toBe([(int) $this->term->id])
        ->and(TermOptions::current()[(int) $this->term->id])->not->toContain('2019');

    // În arhivă: toate, cu anul lipit — altfel două „Semestrul I" ar arăta identic.
    $all = TermOptions::all();

    expect(array_keys($all))->toContain((int) $semVechi->id, (int) $this->term->id)
        ->and($all[(int) $semVechi->id])->toContain('2019–2020');
});

it('fără an curent definit, filtrul nu rămâne gol — cade pe toate semestrele', function () {
    Term::query()->update(['is_current' => false]);

    expect(TermOptions::current())->toBe(TermOptions::all())
        ->and(TermOptions::current())->not->toBeEmpty();
});

it('catalogul oferă clasele anului CURENT, nu pe cele ale anului care n-a început', function () {
    /**
     * Raportul beneficiarului (05.08.2026): deschidea „[DEMO] VIII B" și găsea zero absențe. Nu
     * lipseau datele — clasa aparținea anului URMĂTOR, creat de trecerea în anul nou, care începe
     * pe 1 septembrie. Un an viitor nu POATE avea note sau absențe: gărzile refuză viitorul. Deci
     * nici nu are ce căuta în meniul catalogului, lângă clasele de acum.
     */
    $viitor = AcademicYear::factory()->create([
        'name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
    ]);
    $clasaViitoare = SchoolClass::factory()->for($viitor)->create(['name' => 'ABS-VIITOR', 'section' => null]);

    $user = absNavTeacher($this->ownClass, $this->subject);
    $teacher = User::query()->whereKey($user->id)->sole()->teacher;

    // Același profesor predă și în clasa anului viitor (exact ce face promovarea alocărilor).
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $clasaViitoare->id, 'subject_id' => $this->subject->id,
    ]);

    actingAs($user->fresh());

    $page = Livewire::test(ListAbsences::class)->instance();

    expect(collect($page->catalogEntityCards())->pluck('id')->all())->toBe([$this->ownClass->id]);

    // Nici pe URL: ce nu se poate alege nu se poate nici deschide.
    $direct = Livewire::withQueryParams(['clasa' => (string) $clasaViitoare->id])->test(ListAbsences::class);

    expect($direct->instance()->hasCatalogContext())->toBeFalse();
});

it('profesorul fără nicio clasă în anul curent NU rămâne cu meniul gol', function () {
    // Plasa de siguranță: cine a predat doar în anul încheiat trebuie să-și poată deschide totuși
    // catalogul — altfel „curățăm" lista până la zero.
    $vechi = AcademicYear::factory()->create([
        'name' => '2019–2020', 'starts_on' => '2019-09-01', 'ends_on' => '2020-06-30',
    ]);
    $clasaVeche = SchoolClass::factory()->for($vechi)->create(['name' => 'ABS-VECHI', 'section' => null]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $clasaVeche->id, 'subject_id' => $this->subject->id,
    ]);

    actingAs($user->fresh());

    expect(collect(Livewire::test(ListAbsences::class)->instance()->catalogEntityCards())->pluck('id')->all())
        ->toBe([$clasaVeche->id]);
});

it('în vacanță registrul se deschide pe ultima zi de școală, nu pe un tabel gol', function () {
    // Azi = 15 august: între semestre. „Azi" nu e o zi de școală, deci deschiderea pe ziua curentă
    // ar arăta gol pentru ORICE clasă — se citește drept „lipsesc datele".
    Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

    Term::query()->update(['is_current' => false]);
    $incheiat = Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-02-01', 'ends_on' => '2026-07-31', 'is_current' => true,
    ]);

    actingAs(absNavDirector());

    expect(Livewire::test(ListAbsences::class)->instance()->timeRef()->toDateString())
        ->toBe('2026-07-31');

    // În timpul anului școlar cerința rămâne neatinsă: ziua implicită E ziua accesării.
    Carbon::setTestNow(Carbon::parse($incheiat->starts_on)->addMonth());

    expect(Livewire::test(ListAbsences::class)->instance()->timeRef()->toDateString())
        ->toBe(Carbon::parse($incheiat->starts_on)->addMonth()->toDateString());

    Carbon::setTestNow();
});
