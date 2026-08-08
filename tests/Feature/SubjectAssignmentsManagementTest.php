<?php

/**
 * PROFESORII DISCIPLINEI, din formular (cerința beneficiarului, 07.08.2026): fiecare rând =
 * un profesor cu clasele LUI, atât la creare cât și la editare. Starea trece prin
 * {@see SyncSubjectTeachers} — diff pe anul curent: creare / restaurare geamăn
 * arhivat / retragere soft (istoricul notelor rămâne, cu autorul lui).
 *
 * Managerul de alocări de pe fișă a rămas DOAR registru consultativ (toți anii + arhivate):
 * două suprafețe de scriere pe aceeași pagină s-ar călca una pe alta — formularul,
 * pre-completat la deschidere, ar retrage la salvare orice alocare adăugată din tabel după.
 */

use App\Actions\SyncSubjectTeachers;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Filament\Tables\Table;
use Livewire\Features\SupportTesting\Testable;
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

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->subject = Subject::factory()->create(['name' => 'Fizică', 'grade_levels' => range(6, 12)]);
    $this->teacher = Teacher::factory()->create();
    $this->clasa7 = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);
    $this->clasa8 = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8, 'section' => 'A']);
    $this->clasa2 = SchoolClass::factory()->for($this->year)->create(['grade_level' => 2, 'section' => 'A']);
});

/** Rândurile repeater-ului, cum le trimite formularul. */
function teacherRows(array ...$rows): array
{
    return array_values($rows);
}

it('pagina de editare se randează ÎNTREAGĂ, cu registrul alocărilor cu tot', function () {
    // GET-ul real al paginii — o eroare de descoperire/înregistrare a relation manager-ului
    // apare DOAR aici, nu în testele Livewire izolate pe componentă.
    $this->get(SubjectResource::getUrl('edit', ['record' => $this->subject]))
        ->assertOk()
        ->assertSee('teaching-assignments-relation-manager');
});

it('disciplina se CREEAZĂ cu profesorii ei — echipa se declară din prima', function () {
    Livewire::test(CreateSubject::class)
        ->fillForm([
            'name' => 'Astronomie',
            'grading_type' => GradingType::Numeric->value,
            'grade_levels' => [7, 8],
            'teachers' => teacherRows([
                'teacher_id' => $this->teacher->id,
                'class_ids' => [$this->clasa7->id, $this->clasa8->id],
            ]),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subject = Subject::query()->where('name', 'Astronomie')->sole();

    expect(TeachingAssignment::query()
        ->where('subject_id', $subject->id)
        ->where('teacher_id', $this->teacher->id)
        ->pluck('school_class_id')->sort()->values()->all())
        ->toBe([$this->clasa7->id, $this->clasa8->id]);
});

it('fișa pornește de la starea reală: alocările anului curent, grupate pe profesor', function () {
    $colegUser = Teacher::factory()->create(['last_name' => 'Zamfir']);

    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa7->id,
    ]);
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa8->id,
    ]);
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $colegUser->id, 'school_class_id' => $this->clasa7->id,
    ]);

    $rows = array_values(Livewire::test(EditSubject::class, ['record' => $this->subject->getRouteKey()])
        ->instance()->data['teachers'] ?? []);

    expect($rows)->toHaveCount(2);

    // După fill, Livewire poartă id-urile ca string-uri — comparația se face pe int.
    $claseAle = fn (int $teacherId): array => collect($rows)
        ->firstWhere('teacher_id', $teacherId)['class_ids'];

    expect(collect($claseAle($this->teacher->id))->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->toBe([$this->clasa7->id, $this->clasa8->id])
        ->and(collect($claseAle($colegUser->id))->map(fn ($id): int => (int) $id)->values()->all())
        ->toBe([$this->clasa7->id]);
});

it('scoaterea unei clase RETRAGE alocarea (soft), re-adăugarea o RESTAUREAZĂ — nu o recreează', function () {
    $sync = app(SyncSubjectTeachers::class);

    $rezultat = $sync->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id,
        'class_ids' => [$this->clasa7->id, $this->clasa8->id],
    ]));

    expect($rezultat)->toBe(['created' => 2, 'restored' => 0, 'withdrawn' => 0]);

    $alocare8 = TeachingAssignment::query()
        ->where('school_class_id', $this->clasa8->id)->where('subject_id', $this->subject->id)->sole();

    // Clasa 8 iese din declarație → alocarea ei se retrage soft; cea rămasă nu e atinsă.
    $rezultat = $sync->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id,
        'class_ids' => [$this->clasa7->id],
    ]));

    expect($rezultat)->toBe(['created' => 0, 'restored' => 0, 'withdrawn' => 1])
        ->and($alocare8->refresh()->trashed())->toBeTrue();

    // Re-adăugarea aceleiași perechi: indexul unic vede și rândul șters → RESTAURARE, același id.
    $rezultat = $sync->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id,
        'class_ids' => [$this->clasa7->id, $this->clasa8->id],
    ]));

    expect($rezultat)->toBe(['created' => 0, 'restored' => 1, 'withdrawn' => 0])
        ->and($alocare8->refresh()->trashed())->toBeFalse()
        ->and(TeachingAssignment::withTrashed()->where('subject_id', $this->subject->id)->count())->toBe(2);
});

it('salvarea fișei sincronizează declarația: adaugă colegul, retrage clasa scoasă', function () {
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa7->id,
    ]);
    $coleg = Teacher::factory()->create();

    Livewire::test(EditSubject::class, ['record' => $this->subject->getRouteKey()])
        ->fillForm([
            'teachers' => teacherRows(
                ['teacher_id' => $this->teacher->id, 'class_ids' => [$this->clasa8->id]],
                ['teacher_id' => $coleg->id, 'class_ids' => [$this->clasa7->id]],
            ),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $stare = TeachingAssignment::withTrashed()
        ->where('subject_id', $this->subject->id)
        ->get()
        ->map(fn (TeachingAssignment $a): string => $a->teacher_id.'|'.$a->school_class_id.'|'.($a->trashed() ? 'retrasa' : 'vie'))
        ->sort()->values()->all();

    expect($stare)->toBe([
        $this->teacher->id.'|'.$this->clasa7->id.'|retrasa',
        $this->teacher->id.'|'.$this->clasa8->id.'|vie',
        $coleg->id.'|'.$this->clasa7->id.'|vie',
    ]);
});

it('alocările ANILOR TRECUȚI nu intră în diff — istoria nu se rescrie din formularul de azi', function () {
    $anTrecut = AcademicYear::factory()->create(['is_current' => false]);
    $clasaVeche = SchoolClass::factory()->for($anTrecut)->create(['grade_level' => 7, 'section' => 'V']);

    $istorica = TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $clasaVeche->id,
    ]);

    // Declarația de azi nu o conține — și totuși ea rămâne vie (e a altui an).
    app(SyncSubjectTeachers::class)->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id,
        'class_ids' => [$this->clasa7->id],
    ]));

    expect($istorica->refresh()->trashed())->toBeFalse()
        // ...și nici în prefill nu apare (formularul e al anului curent).
        ->and(collect(app(SyncSubjectTeachers::class)->rowsFor($this->subject))
            ->flatMap(fn (array $row): array => $row['class_ids'])
            ->contains($clasaVeche->id))->toBeFalse();
});

it('clasa de pe o treaptă NEMARCATĂ sau dintr-un an străin nu trece — nici prin POST forjat', function () {
    // UI-ul nici nu oferă clasa a II-a (disciplina e 6–12) — dar payload-ul forjat o încearcă.
    Livewire::test(EditSubject::class, ['record' => $this->subject->getRouteKey()])
        ->fillForm([
            'teachers' => teacherRows([
                'teacher_id' => $this->teacher->id,
                'class_ids' => [$this->clasa2->id],
            ]),
        ])
        ->call('save')
        ->assertHasErrors();

    expect(TeachingAssignment::query()->where('subject_id', $this->subject->id)->exists())->toBeFalse();

    // Apărare în adâncime: chiar sărind peste formular, sincronizarea filtrează singură.
    app(SyncSubjectTeachers::class)->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id,
        'class_ids' => [$this->clasa2->id],
    ]));

    expect(TeachingAssignment::query()->where('subject_id', $this->subject->id)->exists())->toBeFalse();
});

it('același profesor pe două rânduri (aceeași grupă) e refuzat cu mesaj, nu rezolvat tăcut', function () {
    Livewire::test(EditSubject::class, ['record' => $this->subject->getRouteKey()])
        ->fillForm([
            'teachers' => teacherRows(
                ['teacher_id' => $this->teacher->id, 'class_ids' => [$this->clasa7->id]],
                ['teacher_id' => $this->teacher->id, 'class_ids' => [$this->clasa8->id]],
            ),
        ])
        ->call('save')
        ->assertHasErrors();

    expect(TeachingAssignment::query()->where('subject_id', $this->subject->id)->exists())->toBeFalse();
});

it('grupa trăiește doar la limba engleză: pe rânduri separate per grupă; pe alte discipline se ignoră', function () {
    $engleza = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'grade_levels' => range(5, 12)]);

    // Același profesor, două grupe = două rânduri — perechea (profesor, grupă) e identitatea rândului.
    app(SyncSubjectTeachers::class)->execute($engleza, teacherRows(
        ['teacher_id' => $this->teacher->id, 'english_group' => 1, 'class_ids' => [$this->clasa7->id]],
        ['teacher_id' => $this->teacher->id, 'english_group' => 2, 'class_ids' => [$this->clasa7->id]],
    ));

    expect(TeachingAssignment::query()->where('subject_id', $engleza->id)
        ->pluck('english_group')->sort()->values()->all())->toBe([1, 2]);

    // Pe o disciplină NE-engleză, grupa strecurată în payload se ignoră — alocarea iese fără grupă.
    app(SyncSubjectTeachers::class)->execute($this->subject, teacherRows([
        'teacher_id' => $this->teacher->id, 'english_group' => 2, 'class_ids' => [$this->clasa7->id],
    ]));

    expect(TeachingAssignment::query()->where('subject_id', $this->subject->id)->sole()->english_group)->toBeNull();
});

it('registrul de pe fișă e DOAR consultativ — fără acțiuni de scriere', function () {
    // Suprafața de scriere e formularul (sincronizat la salvare); tabelul de dedesubt doar arată.
    $manager = new TeachingAssignmentsRelationManager;
    $manager->ownerRecord = $this->subject;
    $manager->pageClass = EditSubject::class;

    $table = $manager->table(Table::make($manager));

    expect($table->getHeaderActions())->toBe([])
        ->and($table->getRecordActions())->toBe([]);
});

function registrul(Subject $subject): Testable
{
    return Livewire::test(TeachingAssignmentsRelationManager::class, [
        'ownerRecord' => $subject,
        'pageClass' => EditSubject::class,
    ]);
}

it('registrul arată UN rând per profesor, cu clasele lui ca pastile — implicit doar anul curent', function () {
    // Restructurarea 08.08.2026: forma veche = rând per alocare, toți anii, grupat pe clasă
    // (la Matematică: 67 de rânduri pe 7 pagini). Forma nouă = rând per (profesor, grupă),
    // filtrat pe anul CURENT.
    $coleg = Teacher::factory()->create(['last_name' => 'Zamfir']);
    $anTrecut = AcademicYear::factory()->create(['is_current' => false]);
    $clasaVeche = SchoolClass::factory()->for($anTrecut)->create(['grade_level' => 7, 'section' => 'V']);

    // Profesorul: două clase în anul curent (una retrasă) + una în anul trecut.
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa7->id,
    ]);
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa8->id,
    ])->delete();
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $clasaVeche->id,
    ]);
    // Colegul: DOAR în anul trecut — nu are ce căuta în vederea implicită.
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $coleg->id, 'school_class_id' => $clasaVeche->id,
    ]);

    $registru = registrul($this->subject);

    // Implicit (anul curent): un singur rând — al profesorului; pastila retrasă e tot acolo,
    // cu sufixul ei (starea nu se mai ascunde după un filtru de arhivă).
    $registru->assertCountTableRecords(1)
        ->assertSee($this->teacher->last_name)
        ->assertDontSee('Zamfir')
        ->assertSee(trans('panel.resources.teaching_assignments.withdrawn_suffix'));

    // Anul trecut, la un filtru distanță: ambii profesori, câte un rând fiecare.
    $registru->filterTable('year', $anTrecut->id)
        ->assertCountTableRecords(2)
        ->assertSee('Zamfir');

    // Filtrul golit = toți anii — tot agregat pe profesor (2 rânduri, nu 4 alocări).
    $registru->filterTable('year', null)->assertCountTableRecords(2);
});

it('registrul desparte grupele de engleză în rânduri distincte — perechea (profesor, grupă) e identitatea', function () {
    $engleza = Subject::factory()->create(['name' => 'Limba străină 1 (engleza)', 'grade_levels' => range(5, 12)]);

    app(SyncSubjectTeachers::class)->execute($engleza, teacherRows(
        ['teacher_id' => $this->teacher->id, 'english_group' => 1, 'class_ids' => [$this->clasa7->id]],
        ['teacher_id' => $this->teacher->id, 'english_group' => 2, 'class_ids' => [$this->clasa7->id, $this->clasa8->id]],
    ));

    registrul($engleza)->assertCountTableRecords(2);
});

it('filtrul de an oferă DOAR anii cu alocări la disciplină, iar implicitul e anul curent', function () {
    $anGol = AcademicYear::factory()->create(['is_current' => false, 'name' => '2010–2011']);

    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa7->id,
    ]);

    $manager = new TeachingAssignmentsRelationManager;
    $manager->ownerRecord = $this->subject;
    $manager->pageClass = EditSubject::class;

    $filter = $manager->table(Table::make($manager))->getFilter('year');

    // Opțiunile stau pe Select-ul intern al filtrului, fără getter public — le citim de la sursă.
    $optiuni = new ReflectionMethod(TeachingAssignmentsRelationManager::class, 'yearOptions');
    $optiuni->setAccessible(true);

    expect($filter)->not->toBeNull()
        ->and($filter->getDefaultState())->toBe($this->year->id)
        ->and(array_keys($optiuni->invoke($manager)))->toContain($this->year->id)
        ->and(array_keys($optiuni->invoke($manager)))->not->toContain($anGol->id);
});

it('treptele blocate se explică sub bifă: istoricul definitiv altfel decât alocările eliberabile', function () {
    // Treapta 7 = note în catalog (definitiv); treapta 8 = doar alocare (eliberabilă).
    $elev = Student::factory()->create();
    $term = Term::factory()->for($this->year)->create();
    Grade::factory()->create([
        'subject_id' => $this->subject->id, 'school_class_id' => $this->clasa7->id,
        'student_id' => $elev->id, 'term_id' => $term->id,
    ]);
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa8->id,
    ]);

    $form = new ReflectionMethod(SubjectForm::class, 'lockedGradeDetails');
    $form->setAccessible(true);

    expect($form->invoke(null, $this->subject->fresh()))->toBe([
        'history' => [7],
        'assignments' => [8],
    ]);
});

it('starea venită din client fără treptele blocate le primește înapoi — „Debifează toate" nu poate rupe istoricul', function () {
    TeachingAssignment::factory()->create([
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'school_class_id' => $this->clasa7->id,
    ]);

    $component = Livewire::test(EditSubject::class, ['record' => $this->subject->getRouteKey()])
        // Simulează debifarea totală venită din browser (bulk toggle / stare forjată).
        ->set('data.grade_levels', [6]);

    $stare = collect($component->instance()->data['grade_levels'] ?? [])
        ->map(fn ($grade): int => (int) $grade)->sort()->values()->all();

    // Treapta 7 (blocată de alocare) s-a întors singură; 6 a rămas cum a cerut clientul.
    expect($stare)->toContain(7)->toContain(6);
});
