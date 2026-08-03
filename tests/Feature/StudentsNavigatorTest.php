<?php

/**
 * Navigatorul paginii „Elevi" — adaptat: singura dimensiune e „Clase" (elevul se leagă de clasă
 * prin ÎNMATRICULARE), iar administrația păstrează registrul complet prin vederea „Arhivă".
 */

use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
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
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->ownClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ST-A', 'section' => null]);
    $this->foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'ST-B', 'section' => null]);
    $this->subject = Subject::factory()->create();

    $this->ownStudent = Student::factory()->create();
    Enrollment::factory()->for($this->ownStudent)->for($this->ownClass)->for($this->year)->create();
    $this->foreignStudent = Student::factory()->create();
    Enrollment::factory()->for($this->foreignStudent)->for($this->foreignClass)->for($this->year)->create();

    // Elev PLECAT: fără nicio înmatriculare — invizibil pe carduri, dar prezent în Arhivă.
    $this->orphanStudent = Student::factory()->create();
});

function studentsNavTeacher(SchoolClass $class, Subject $subject): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    return $user;
}

function studentsNavDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);

    return $user;
}

it('elevii au o singură dimensiune (Clase), cu carduri doar din perimetrul profesorului', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::test(ListStudents::class);

    expect($component->instance()->catalogDimensions())->toHaveKey('clase')->toHaveCount(1)
        ->and(collect($component->instance()->catalogEntityCards())->pluck('id')->all())->toBe([$this->ownClass->id]);
});

it('contextul clasei arată DOAR elevii înmatriculați în ea', function () {
    actingAs(studentsNavDirector());

    Livewire::test(ListStudents::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertCanSeeTableRecords([$this->ownStudent])
        ->assertCanNotSeeTableRecords([$this->foreignStudent, $this->orphanStudent]);
});

it('clasa străină venită prin URL nu deschide context pentru profesor', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['clasa' => (string) $this->foreignClass->id])
        ->test(ListStudents::class);

    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

it('ARHIVA (administrație): registrul complet, inclusiv elevii fără înmatriculare', function () {
    actingAs(studentsNavDirector());

    Livewire::withQueryParams(['arhiva' => '1'])
        ->test(ListStudents::class)
        ->assertCanSeeTableRecords([$this->ownStudent, $this->foreignStudent, $this->orphanStudent]);
});

it('ARHIVA nu e accesibilă profesorului (flag-ul din URL se ignoră)', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $component = Livewire::withQueryParams(['arhiva' => '1'])->test(ListStudents::class);

    // Fără context (navigatorul rămâne pe carduri) — arhiva cere isAdministrator.
    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

it('ieșirea din context curăță și flag-ul de arhivă', function () {
    actingAs(studentsNavDirector());

    $component = Livewire::withQueryParams(['arhiva' => '1'])->test(ListStudents::class);
    expect($component->instance()->hasCatalogContext())->toBeTrue();

    $component->call('leaveCatalogContext');
    expect($component->instance()->hasCatalogContext())->toBeFalse();
});

// ── Căutarea de pe aterizare (cerința 2026-08-03) ───────────────────────────────────────────

it('CĂUTARE: elevul se găsește după nume direct din meniu, cu salt în fișa lui', function () {
    actingAs(studentsNavDirector());

    $this->ownStudent->update(['last_name' => 'Zaharescu', 'first_name' => 'Mihai']);

    $hits = Livewire::withQueryParams(['cauta' => 'zahar'])
        ->test(ListStudents::class)
        ->instance()
        ->catalogSearchHits();

    expect($hits)->toHaveCount(1)
        ->and($hits[0]['id'])->toBe($this->ownStudent->id)
        ->and($hits[0]['title'])->toContain('Zaharescu')
        ->and($hits[0]['meta'])->toContain('ST-A')
        ->and($hits[0]['url'])->toContain((string) $this->ownStudent->id);
});

it('CĂUTARE: „nume prenume" și „prenume nume" duc la același elev; matricolul la fel', function () {
    actingAs(studentsNavDirector());

    $this->ownStudent->update(['last_name' => 'Zaharescu', 'first_name' => 'Mihai', 'register_number' => 'MX-4417']);

    $hitsFor = fn (string $term): array => Livewire::withQueryParams(['cauta' => $term])
        ->test(ListStudents::class)
        ->instance()
        ->catalogSearchHits();

    expect(collect($hitsFor('Zaharescu Mihai'))->pluck('id')->all())->toBe([$this->ownStudent->id])
        ->and(collect($hitsFor('Mihai Zaharescu'))->pluck('id')->all())->toBe([$this->ownStudent->id])
        ->and(collect($hitsFor('MX-4417'))->pluck('id')->all())->toBe([$this->ownStudent->id]);
});

it('CĂUTARE: profesorul NU găsește elevii din afara claselor lui', function () {
    actingAs(studentsNavTeacher($this->ownClass, $this->subject));

    $this->foreignStudent->update(['last_name' => 'Zaharescu', 'first_name' => 'Mihai']);

    $hits = Livewire::withQueryParams(['cauta' => 'zahar'])
        ->test(ListStudents::class)
        ->instance()
        ->catalogSearchHits();

    expect($hits)->toBe([]);
});

it('CĂUTARE: o singură literă nu declanșează lista (zgomot)', function () {
    actingAs(studentsNavDirector());

    $hits = Livewire::withQueryParams(['cauta' => 'a'])
        ->test(ListStudents::class)
        ->instance()
        ->catalogSearchHits();

    expect($hits)->toBe([]);
});

it('CĂUTARE: aceeași casetă filtrează și cardurile de clasă', function () {
    actingAs(studentsNavDirector());

    $cards = Livewire::withQueryParams(['cauta' => 'ST-A'])
        ->test(ListStudents::class)
        ->instance()
        ->catalogEntityCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->ownClass->id]);
});

it('CĂUTARE: deschiderea unei clase curăță termenul (comutatorul de surori rămâne complet)', function () {
    actingAs(studentsNavDirector());

    $component = Livewire::withQueryParams(['cauta' => 'ST-A'])
        ->test(ListStudents::class)
        ->call('openCatalogEntity', $this->ownClass->id);

    expect($component->instance()->catalogSearchTerm())->toBe('')
        ->and(array_keys($component->instance()->catalogSiblingOptions()))
        ->toContain($this->ownClass->id, $this->foreignClass->id);
});

it('GRUPARE: cardurile se așază pe cicluri, în ordinea școlii', function () {
    actingAs(studentsNavDirector());

    SchoolClass::factory()->for($this->year)->create(['name' => 'ST-L', 'section' => null, 'grade_level' => 11]);
    SchoolClass::factory()->for($this->year)->create(['name' => 'ST-P', 'section' => null, 'grade_level' => 3]);
    $this->ownClass->update(['grade_level' => 7]);
    $this->foreignClass->update(['grade_level' => 7]);

    $groups = Livewire::test(ListStudents::class)->instance()->catalogCardGroups();

    expect(collect($groups)->pluck('label')->all())->toBe([
        __('panel.catalog_nav.cycles.primar'),
        __('panel.catalog_nav.cycles.gimnaziu'),
        __('panel.catalog_nav.cycles.liceu'),
    ])->and(collect($groups[1]['cards'])->pluck('id')->all())
        ->toEqualCanonicalizing([$this->ownClass->id, $this->foreignClass->id]);
});

// ── Coloana „Clasa" în arhivă ───────────────────────────────────────────────────────────────

it('ARHIVA arată clasa fiecărui elev; în contextul unei clase coloana dispare', function () {
    actingAs(studentsNavDirector());

    Livewire::withQueryParams(['arhiva' => '1'])
        ->test(ListStudents::class)
        ->assertTableColumnVisible('current_class')
        ->assertTableColumnStateSet('current_class', 'ST-A', $this->ownStudent)
        ->assertTableColumnStateSet('current_class', null, $this->orphanStudent);

    // ⚠️ withQueryParams rămâne setat pe managerul Livewire până la sfârșitul testului — al
    // doilea component trebuie să pornească explicit cu URL curat, altfel rămâne în arhivă.
    Livewire::withQueryParams([])
        ->test(ListStudents::class)
        ->call('openCatalogEntity', $this->ownClass->id)
        ->assertTableColumnHidden('current_class');
});
