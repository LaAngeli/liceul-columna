<?php

/**
 * CATALOGUL CLASEI (borderoul) — cerința beneficiarului (2026-07-30): introducerea notelor și a
 * absențelor pentru TOATĂ clasa dintr-un singur ecran, în 2-3 minute, cu vizualizare de ansamblu.
 *
 * Testele fixează cele două jumătăți ale promisiunii:
 *  - VITEZA e reală: un batch întreg (note + absențe pe mai mulți elevi) intră dintr-o singură
 *    acțiune, prin MODELE — observerii recalculează mediile și notifică familia;
 *  - GRANIȚELE ȚIN: aceleași gărzi ca formularele clasice (scope pe server per rând, atomicitate
 *    la eroare, fără duplicate, fără viitor), aceeași vizibilitate ca resursele de catalog.
 */

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Filament\Pages\ClassRegister;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Widgets\QuickActions;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year)->create([
        'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(2),
        'ends_on' => Carbon::today()->addMonths(2),
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'V', 'section' => 'A', 'grade_level' => 5]);
    $this->subject = Subject::factory()->create(['grading_type' => 'n']);

    $profUser = User::factory()->create();
    $profUser->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $profUser->id]);
    $this->profUser = $profUser->fresh();

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // Trei elevi, în ordine alfabetică inversă la creare — borderoul îi sortează el.
    $this->students = collect(['Zamfir', 'Munteanu', 'Albu'])->map(function (string $name) {
        $student = Student::factory()->create(['last_name' => $name, 'first_name' => 'Elev']);
        Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create([
            'enrolled_on' => Carbon::today()->subMonths(3),
            'left_on' => null,
        ]);

        return $student;
    });
});

it('scrierea din panoul zilei trece prin modele — autor, semestru, medie recalculată', function () {
    actingAs($this->profUser);

    [$a, , $c] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta');
    $page->call('addDayAbsence', $c->id, $azi);

    $gradeA = Grade::query()->where('student_id', $a->id)->sole();

    expect((int) $gradeA->teacher_id)->toBe($this->teacher->id)
        ->and((int) $gradeA->term_id)->toBe($this->term->id)
        ->and((float) $gradeA->value)->toBe(9.0)
        // Absența: FĂRĂ statut (profesorul consemnează, dirigintele decide), pe aceeași disciplină.
        ->and(Absence::query()->sole()->student_id)->toBe($c->id)
        ->and(Absence::query()->sole()->is_motivated)->toBeNull()
        // Observer-ul a lucrat: media semestrială există deja.
        ->and(TermAverage::query()->where('student_id', $a->id)->where('subject_id', $this->subject->id)->exists())->toBeTrue();
});

it('rândurile ies alfabetic, cu notele și media elevului', function () {
    actingAs($this->profUser);

    $rows = Livewire::test(ClassRegister::class)->instance()->rows();

    expect(array_map(fn (array $row): string => (string) $row['student']->last_name, $rows))
        ->toBe(['Albu', 'Munteanu', 'Zamfir']);
});

it('fără orar, apăsările repetate umplu ordinalele — absența istorică „fără oră" nu blochează', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    // Absență istorică fără oră (consemnarea rapidă de dinainte de sloturi).
    Absence::query()->create([
        'student_id' => $a->id,
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->teacher->id,
        'occurred_on' => $azi,
        'is_motivated' => false,
    ]);

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayAbsence', $a->id, $azi);
    $page->call('addDayAbsence', $a->id, $azi);

    expect(Absence::query()->where('student_id', $a->id)->orderByRaw('lesson_number IS NULL, lesson_number')->pluck('lesson_number')->all())
        ->toBe([1, 2, null]);
});

it('nu vede și nu scrie pe clasa altui profesor — nici prin panoul zilei', function () {
    $other = SchoolClass::factory()->for($this->year)->create(['name' => 'VI', 'section' => 'B', 'grade_level' => 6]);
    $stranger = Student::factory()->create();
    Enrollment::factory()->for($stranger)->for($other)->for($this->year)->create(['left_on' => null]);

    actingAs($this->profUser);

    $component = Livewire::withQueryParams(['clasa' => (string) $other->id])->test(ClassRegister::class);

    // Parametrul străin cade pe prima clasă PERMISĂ — nu pe clasa cerută.
    expect($component->instance()->activeClass()?->id)->toBe($this->class->id);

    // Iar un id de elev STRĂIN de clasa activă e refuzat înainte de orice gardă de model.
    $component->call('addDayGrade', $stranger->id, Carbon::today()->toDateString(), '10', 'curenta');
    $component->call('addDayAbsence', $stranger->id, Carbon::today()->toDateString());

    expect(Grade::query()->where('student_id', $stranger->id)->count())->toBe(0)
        ->and(Absence::query()->where('student_id', $stranger->id)->count())->toBe(0);
});

it('dirigintele vede disciplina altuia, nu notează la ea, dar consemnează absențe', function () {
    // Dirigintele clasei predă altă disciplină; disciplina din test rămâne a profesorului.
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Profesor->value);
    $homeroomTeacher = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroomTeacher->id]);

    actingAs($homeroomUser->fresh());

    $component = Livewire::withQueryParams(['disciplina' => (string) $this->subject->id])->test(ClassRegister::class);
    $page = $component->instance();

    expect($page->activeSubject()?->getKey())->toBe($this->subject->id)
        ->and($page->canEnterGrades())->toBeFalse()
        ->and($page->canRecordAbsences())->toBeTrue();

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $component->call('addDayGrade', $a->id, $azi, '9', 'curenta');
    $component->call('addDayAbsence', $a->id, $azi);

    // Nota NU s-a creat (nu are dreptul); absența DA, sub numele lui — FĂRĂ statut: chiar și
    // dirigintele o statutează apoi, ca decizie separată de consemnare.
    $absence = Absence::query()->sole();

    expect(Grade::query()->count())->toBe(0)
        ->and((int) $absence->teacher_id)->toBe($homeroomTeacher->id)
        ->and($absence->is_motivated)->toBeNull();
});

it('administratorul tehnic și familia nu accesează pagina', function () {
    foreach ([UserRole::AdministratorTehnic, UserRole::Elev, UserRole::Parinte] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        actingAs($user->fresh());

        expect(ClassRegister::canAccess())->toBeFalse();
    }

    // Directorul, în schimb, intră și poate nota (autoritate academică).
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director->fresh());

    expect(ClassRegister::canAccess())->toBeTrue()
        ->and(Livewire::test(ClassRegister::class)->instance()->canEnterGrades())->toBeTrue();
});

it('disciplina pe calificativ acceptă simbolul scalei și refuză textul liber', function () {
    $this->subject->update(['grading_type' => 'c']);
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $azi, 'FB', 'curenta');

    $grade = Grade::query()->where('student_id', $a->id)->sole();

    expect($grade->calificativ)->toBe('FB')
        ->and($grade->value)->toBeNull();

    Livewire::test(ClassRegister::class)->call('addDayGrade', $b->id, $azi, 'PREA-LUNG-XX', 'curenta');

    expect(Grade::query()->where('student_id', $b->id)->count())->toBe(0);
});

it('nota nu poate fi în viitor — gardă moștenită din traitul formularului', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, Carbon::tomorrow()->toDateString(), '9', 'curenta');

    expect(Grade::query()->count())->toBe(0)
        // Panoul o spune dinainte: pe o zi viitoare, formularul de notă nici nu se oferă.
        ->and($page->instance()->dayPanel($a->id, Carbon::tomorrow()->toDateString())['can_grade'])->toBeFalse();
});

it('meniul zilnic: borderoul e acțiunea primară din dashboard și e legat din contextul Note', function () {
    actingAs($this->profUser);

    // Banda „Acțiuni rapide": Catalogul clasei apare (primul, ca acțiune primară).
    Livewire::test(QuickActions::class)
        ->assertSee(trans('panel.class_register.title'));

    // Din contextul unei clase în Note, puntea spre borderou e la un click. (Profesorul cu o
    // singură clasă intră AUTOMAT în contextul ei — puntea îi apare chiar de la aterizare.)
    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListGrades::class)
        ->assertSee(trans('panel.class_register.title'));

    // În Elevi — același navigator, alt scop — puntea NU se afișează (doar în Note/Absențe).
    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListStudents::class)
        ->assertDontSee(trans('panel.class_register.title'));
});

it('pagina se randează cu elevii și îndrumarea spre panoul zilei', function () {
    actingAs($this->profUser);

    Livewire::test(ClassRegister::class)
        ->assertOk()
        ->assertSee('Albu')
        ->assertSee('Zamfir')
        ->assertSee(trans('panel.class_register.cell_hint'))
        // Aparatul de introducere în masă a dispărut (05.08.2026): fără „Salvează tot".
        ->assertDontSee(trans('panel.class_register.save_all'));
});

// ── Corecturile din 2026-07-30 (data = sursă unică) ────────────────────────────────────────────

it('semestrul vizualizat e cel CURENT — scrierea și-l derivă din ziua celulei, pe server', function () {
    Term::factory()->for($this->year)->create([
        'number' => 2,
        'name' => 'Semestrul I',
        'is_current' => false,
        'starts_on' => Carbon::today()->subMonths(6),
        'ends_on' => Carbon::today()->subMonths(3),
    ]);

    actingAs($this->profUser);

    expect(Livewire::test(ClassRegister::class)->instance()->activeTerm()?->id)->toBe($this->term->id);
});

// ── Absența: UN marcaj, fără statut — profesorul consemnează, dirigintele decide (04.08.2026) ──

it('nu mai randează selector de semestru, dar data notei se citește la survol', function () {
    Term::factory()->for($this->year)->create([
        'number' => 2,
        'name' => 'Semestrul I',
        'is_current' => false,
        'starts_on' => Carbon::today()->subMonths(6),
        'ends_on' => Carbon::today()->subMonths(3),
    ]);

    actingAs($this->profUser);

    $a = $this->students->first();
    Grade::query()->create([
        'student_id' => $a->id,
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->teacher->id,
        'graded_on' => Carbon::today()->subDays(3),
        'evaluation_type' => EvaluationType::Curenta->value,
        'value' => 9,
    ]);

    $html = Livewire::test(ClassRegister::class)->assertOk()->html();

    // Selectorul de semestru a dispărut (data îl decide), la fel butonul lui.
    expect($html)->not->toContain('openTerm')
        // Data aplicării notei e din nou la survol, formulată explicit (restabilit 2026-07-30).
        ->and($html)->toContain(trans('panel.class_register.grade_tooltip', [
            'value' => '9',
            'type' => EvaluationType::Curenta->label(),
            'date' => Carbon::today()->subDays(3)->format('d.m.Y'),
        ]));
});

it('o zi de DUPĂ finalul anului e refuzată la scriere; una din interiorul anului trece', function () {
    // Anul s-a încheiat luna trecută. Gărzile de server refuză ziua de azi (nu aparține niciunui
    // semestru al anului), dar o zi din interiorul anului încheiat rămâne completabilă.
    DB::table('terms')->where('id', $this->term->id)->update([
        'starts_on' => Carbon::today()->subMonths(5)->toDateString(),
        'ends_on' => Carbon::today()->subMonth()->toDateString(),
    ]);
    DB::table('academic_years')->where('id', $this->year->id)->update([
        'starts_on' => Carbon::today()->subMonths(11)->toDateString(),
        'ends_on' => Carbon::today()->subMonth()->toDateString(),
    ]);

    actingAs($this->profUser);

    $a = $this->students->first();
    $page = Livewire::test(ClassRegister::class);

    $page->call('addDayGrade', $a->id, Carbon::today()->toDateString(), '9', 'curenta');
    $page->call('addDayAbsence', $a->id, Carbon::today()->toDateString());

    expect(Grade::query()->count())->toBe(0)
        ->and(Absence::query()->count())->toBe(0);

    $page->call('addDayGrade', $a->id, Carbon::today()->subMonths(2)->toDateString(), '9', 'curenta');

    expect(Grade::query()->count())->toBe(1);
});

it('o zi din vacanța DIN interiorul anului rămâne salvabilă, prin fallback-ul semestrului', function () {
    DB::table('terms')->where('id', $this->term->id)->update([
        'starts_on' => Carbon::today()->addMonth()->toDateString(),
        'ends_on' => Carbon::today()->addMonths(4)->toDateString(),
    ]);
    DB::table('academic_years')->where('id', $this->year->id)->update([
        'starts_on' => Carbon::today()->subMonths(4)->toDateString(),
        'ends_on' => Carbon::today()->addMonths(6)->toDateString(),
    ]);

    actingAs($this->profUser);

    $a = $this->students->first();

    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, Carbon::today()->toDateString(), '9', 'curenta');

    expect(Grade::query()->count())->toBe(1)
        ->and((int) Grade::query()->sole()->term_id)->toBe($this->term->id);
});

it('teza intră cu tipul ales din panoul zilei și se citește pe filtrul ei', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    Livewire::test(ClassRegister::class)
        ->call('addDayGrade', $a->id, Carbon::today()->toDateString(), '8', EvaluationType::Teza->value);

    expect(Grade::query()->sole()->evaluation_type)->toBe(EvaluationType::Teza);

    // Borderoul se citește pe UN tip (implicit „Curentă", 04.08.2026) — teza se vede alegând-o.
    $rows = Livewire::test(ClassRegister::class)
        ->set('gradeTypeFilter', EvaluationType::Teza->value)
        ->instance()
        ->rows();
    $rowA = collect($rows)->first(fn (array $row): bool => $row['student']->id === $a->id);

    expect($rowA['grades'])->toHaveCount(1)
        ->and($rowA['grades'][0]['value'])->toBe('8')
        ->and($rowA['grades'][0]['weighted'])->toBeTrue();
});

// ─── Un profesor, DOUĂ discipline la aceeași clasă (cerința beneficiarului, 01.08.2026) ──
//
// Scenariu real al școlii (profesorul de istorie ține și „Educație pentru societate"), care
// lipsea din zona demo: fiecare cont pedagogic avea cel mult o disciplină per clasă, deci nu se
// putea verifica dacă profesorul chiar poate comuta între ele după ce alege clasa.

it('profesorul cu două discipline la aceeași clasă le vede pe AMÂNDOUĂ și poate comuta', function () {
    $second = Subject::factory()->create(['name' => 'Biologie', 'grading_type' => 'n']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $second->id,
    ]);

    actingAs($this->profUser);

    $options = Livewire::test(ClassRegister::class)->instance()->subjectOptions();

    // Ambele apar, ambele marcate ca „ale mele" (poate nota la fiecare).
    expect(collect($options)->pluck('id')->all())
        ->toEqualCanonicalizing([$this->subject->id, $second->id])
        ->and(collect($options)->pluck('mine')->all())->toBe([true, true]);

    // Comutarea prin `?disciplina=` schimbă efectiv disciplina activă a borderoului.
    foreach ([$this->subject->id, $second->id] as $subjectId) {
        $active = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'disciplina' => (string) $subjectId])
            ->test(ClassRegister::class)
            ->instance()
            ->activeSubject();

        expect($active?->id)->toBe($subjectId);
    }
});

it('notele intră pe disciplina ACTIVĂ, nu pe prima a clasei', function () {
    // Al doilea obiect al ACELUIAȘI profesor la aceeași clasă — comutarea trebuie să conteze.
    $history = Subject::factory()->create(['name' => 'Istorie', 'grading_type' => 'n']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $history->id,
    ]);

    actingAs($this->profUser);

    $a = $this->students->first();

    // Cu a DOUA disciplină aleasă, nota din panoul zilei intră exact pe ea.
    Livewire::withQueryParams(['disciplina' => (string) $history->id])
        ->test(ClassRegister::class)
        ->call('addDayGrade', $a->id, Carbon::today()->toDateString(), '9', 'curenta');

    $grade = Grade::query()->sole();

    expect((int) $grade->subject_id)->toBe($history->id);
});

// ── Filtre de citire + aliniere pe coloane de dată (04.08.2026) ───────────────────────────────
// Cerința beneficiarului: notele stăteau una lângă alta, pe rândul elevului, deci „colonița" unei
// zile — sau a ESS-ului — nu se putea urmări cu ochiul. Filtrele restrâng CE se vede, iar când
// zilele rămase încap, notele se așază în coloane pe dată.

/** Notă la (clasa, disciplina, semestrul) testului, pentru un elev anume. */
function registerGrade(Student $student, string $date, int $value, EvaluationType $type = EvaluationType::Curenta): Grade
{
    return Grade::factory()->create([
        'student_id' => $student->id,
        'subject_id' => test()->subject->id,
        'school_class_id' => test()->class->id,
        'term_id' => test()->term->id,
        'teacher_id' => test()->teacher->id,
        'graded_on' => $date,
        'value' => $value,
        'evaluation_type' => $type,
    ]);
}

it('notele se aliniază pe coloane de dată — cine n-a fost notat are celula goală', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    $zi1 = Carbon::today()->subDays(10)->toDateString();
    $zi2 = Carbon::today()->subDays(3)->toDateString();

    registerGrade($a, $zi1, 9);
    registerGrade($a, $zi2, 7);
    registerGrade($b, $zi2, 8); // $b lipsește din prima zi — exact golul care trebuie să se vadă

    // Intervalul LIBER acoperă ambele zile: pastila „Toate" nu mai există în borderou
    // (decizia beneficiarului, 05.08.2026), iar zilele testului pot cădea în luni diferite.
    $page = Livewire::test(ClassRegister::class)
        ->set('timeMode', 'personalizat')
        ->set('timeFrom', $zi1)
        ->set('timeUntil', $zi2)
        ->instance();

    // Rigla de date: zilele distincte, cronologic — aceeași pentru antet și pentru toate rândurile.
    expect(array_column($page->gradeColumns(), 'iso'))->toBe([$zi1, $zi2])
        ->and($page->gradesAlignedByDate())->toBeTrue();

    $rows = collect($page->rows())->keyBy(fn (array $row): int => (int) $row['student']->id);

    expect(array_keys($rows[$a->id]['gradesByDate']))->toBe([$zi1, $zi2])
        ->and($rows[$a->id]['gradesByDate'][$zi1][0]['value'])->toBe('9')
        // Elevul fără notă în prima zi NU are cheia — celula rămâne goală, nu se decalează.
        ->and(array_keys($rows[$b->id]['gradesByDate']))->toBe([$zi2]);
});

it('filtrul pe tip lasă doar sumativele, fără să ascundă vreun elev', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    registerGrade($a, Carbon::today()->subDays(10)->toDateString(), 9);
    registerGrade($a, Carbon::today()->subDays(2)->toDateString(), 6, EvaluationType::Teza);
    registerGrade($b, Carbon::today()->subDays(10)->toDateString(), 8);

    $page = Livewire::test(ClassRegister::class)->set('gradeTypeFilter', EvaluationType::Teza->value);
    $instance = $page->instance();

    $rows = collect($instance->rows())->keyBy(fn (array $row): int => (int) $row['student']->id);

    // O singură coloană (ziua tezei), iar clasa rămâne întreagă: golul e informația.
    expect($instance->gradeColumns())->toHaveCount(1)
        ->and($rows)->toHaveCount(3)
        ->and($rows[$a->id]['grades'])->toHaveCount(1)
        ->and($rows[$a->id]['grades'][0]['value'])->toBe('6')
        ->and($rows[$b->id]['grades'])->toBe([]);
});

it('bara temporală restrânge la perioada aleasă — aceeași ca în Note', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    $zi1 = Carbon::today()->subDays(10)->toDateString();
    $zi2 = Carbon::today()->subDays(3)->toDateString();

    registerGrade($a, $zi1, 9);
    registerGrade($a, $zi2, 7);
    registerGrade($b, $zi2, 8);

    // Modul „Zi" pe ziua întâi: o singură coloană, iar ziua a doua rămâne pe dinafară.
    $page = Livewire::test(ClassRegister::class)
        ->set('timeMode', 'zi')
        ->set('timeRef', $zi1);

    $instance = $page->instance();
    $rows = collect($instance->rows())->keyBy(fn (array $row): int => (int) $row['student']->id);

    expect(array_column($instance->gradeColumns(), 'iso'))->toBe([$zi1])
        ->and($rows[$a->id]['grades'])->toHaveCount(1)
        ->and($rows[$b->id]['grades'])->toBe([])
        // Elevii NU dispar niciodată — golul e informația.
        ->and($rows)->toHaveCount(3);

    // Istoricul complet se cere prin intervalul LIBER cu capete deschise — „Toate" a ieșit
    // din borderou; un ‹?mod=toate› rămas într-un URL vechi cade pe implicitul paginii (luna).
    $all = Livewire::test(ClassRegister::class)
        ->set('timeMode', 'personalizat')
        ->set('timeFrom', $zi1)
        ->set('timeUntil', $zi2)
        ->instance();

    expect(array_column($all->gradeColumns(), 'iso'))->toBe([$zi1, $zi2]);
});

it('catalogul se deschide pe LUNA curentă, cu istoricul la o pastilă distanță', function () {
    /**
     * Cerința beneficiarului (04.08.2026): cu implicitul „Toate", clasele cu un an de istoric
     * depășeau pragul de coloane și cădeau în șir, așa că 1B și 7B arătau două tabele diferite.
     * Pe o lună, forma e una singură. Datele testului sunt alese să nu depindă de ziua rulării:
     * azi e mereu în luna curentă, acum 40 de zile e mereu în afara ei.
     */
    actingAs($this->profUser);

    $a = $this->students->first();
    registerGrade($a, Carbon::today()->toDateString(), 9);
    registerGrade($a, Carbon::today()->subDays(40)->toDateString(), 5);

    $page = Livewire::test(ClassRegister::class)->instance();

    expect($page->timeMode())->toBe('luna')
        ->and(array_column($page->gradeColumns(), 'iso'))->toBe([Carbon::today()->toDateString()]);

    // „Toate" NU mai există în borderou (05.08.2026): pastila lipsește din bară, iar un
    // ‹?mod=toate› venit din URL cade pe implicitul paginii — luna — nu pe tot istoricul.
    $all = Livewire::test(ClassRegister::class)->set('timeMode', 'toate')->instance();

    expect($all->timeMode())->toBe('luna')
        ->and(collect($all->timePills())->pluck('key'))->not->toContain('toate')
        ->and(array_column($all->gradeColumns(), 'iso'))->toBe([Carbon::today()->toDateString()]);
});

it('multe zile nu schimbă forma — tot coloane, doar mai lat', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    // 30 de zile distincte — peste fostul prag care arunca notele înapoi în șir și făcea ca
    // două clase să arate diferit pe aceeași pastilă „Toate" (cerința beneficiarului, 04.08.2026).
    for ($i = 0; $i < 30; $i++) {
        registerGrade($a, Carbon::today()->subDays(40 - $i)->toDateString(), 8);
    }

    $page = Livewire::test(ClassRegister::class)
        ->set('timeMode', 'personalizat')
        ->set('timeFrom', Carbon::today()->subDays(40)->toDateString())
        ->set('timeUntil', Carbon::today()->toDateString())
        ->instance();

    expect($page->gradeColumns())->toHaveCount(30)
        // O SINGURĂ formă: coloanele rămân, indiferent de volum.
        ->and($page->gradesAlignedByDate())->toBeTrue()
        ->and(collect($page->rows())->firstWhere('student.id', $a->id)['grades'])->toHaveCount(30);
});
it('filtrele de citire nu ating scrierea: nota intră chiar dacă filtrul n-o arată', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    registerGrade($a, Carbon::today()->subDays(5)->toDateString(), 9, EvaluationType::Teza);

    // Filtrăm la teze (deci $b nu are nicio notă vizibilă) și totuși îi punem notă CURENTĂ.
    Livewire::test(ClassRegister::class)
        ->set('gradeTypeFilter', EvaluationType::Teza->value)
        ->call('addDayGrade', $b->id, Carbon::today()->toDateString(), '7', 'curenta');

    expect(Grade::query()->where('student_id', $b->id)->count())->toBe(1);
});

it('tipul e implicit „Curentă" și nu există opțiunea „toate tipurile"', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    registerGrade($a, Carbon::today()->subDays(4)->toDateString(), 9);
    registerGrade($a, Carbon::today()->subDays(2)->toDateString(), 6, EvaluationType::Teza);

    // Testul e despre filtrul de TIP; perioada nu trebuie să-i taie zilele (implicitul = luna).
    $page = Livewire::test(ClassRegister::class)->set('timeMode', 'toate');
    $instance = $page->instance();

    // Amestecul de tipuri pe același rând era chiar starea din care nu se putea citi nimic:
    // se alege mereu UN tip, deci coloana înseamnă ceva.
    expect($page->get('gradeTypeFilter'))->toBe(EvaluationType::Curenta->value)
        ->and(array_keys($instance->gradeTypeOptions()))
        ->toBe(array_map(fn (EvaluationType $type): string => $type->value, EvaluationType::cases()));

    $rows = collect($instance->rows())->keyBy(fn (array $row): int => (int) $row['student']->id);

    // Implicit se văd DOAR notele curente — teza rămâne pe dinafară până e cerută.
    expect($rows[$a->id]['grades'])->toHaveCount(1)
        ->and($rows[$a->id]['grades'][0]['value'])->toBe('9');
});

// ── Panoul zilei + identitatea de oră (05.08.2026) ─────────────────────────────────────────

it('două ore consecutive ale aceleiași discipline = două absențe distincte, pe ore', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    // Orarul: Biologia (disciplina fixture) are DOUĂ ore consecutive azi.
    foreach ([2, 3] as $hour) {
        Lesson::query()->create([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'x',
            'teacher_name' => 'x',
            'day_of_week' => Carbon::today()->isoWeekday(),
            'lesson_number' => $hour,
        ]);
    }

    $page = Livewire::test(ClassRegister::class);

    // Panoul oferă exact orele din orar.
    expect($page->instance()->timetableHours($azi))->toBe([2, 3]);

    // Absent la AMBELE ore — două consemnări, fiecare pe ora ei.
    $page->call('addDayAbsence', $a->id, $azi, 2)
        ->call('addDayAbsence', $a->id, $azi, 3);

    expect(Absence::query()->where('student_id', $a->id)->whereDate('occurred_on', $azi)->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([2, 3]);

    // Aceeași oră a doua oară = duplicat, refuzat pe server.
    $page->call('addDayAbsence', $a->id, $azi, 3);

    expect(Absence::query()->where('student_id', $a->id)->whereDate('occurred_on', $azi)->count())->toBe(2)
        // Ambele pleacă FĂRĂ statut: profesorul consemnează, dirigintele decide.
        ->and(Absence::query()->where('student_id', $a->id)->whereNull('is_motivated')->count())->toBe(2);
});

it('absent la o oră, notă la cealaltă — ziua le poartă pe amândouă, distinct', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    // Nota orei la care a fost prezent + absența orei la care a lipsit — ambele din panoul zilei.
    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta');
    $page->call('addDayAbsence', $a->id, $azi);

    $rows = collect(Livewire::test(ClassRegister::class)->instance()->rows())
        ->keyBy(fn (array $row): int => (int) $row['student']->id);

    // Rândul elevului poartă ambele fapte pe aceeași zi — nota ȘI absența orei atribuite automat.
    expect($rows[$a->id]['gradesByDate'][$azi][0]['value'])->toBe('9')
        ->and($rows[$a->id]['absencesByDate'][$azi])->toHaveCount(1)
        ->and($rows[$a->id]['absencesByDate'][$azi][0]['lesson'])->toBe(1);

    // Panoul zilei le arată împreună, cu pârghiile privitorului.
    $panel = Livewire::test(ClassRegister::class)->instance()->dayPanel($a->id, $azi);

    expect($panel['grades'])->toHaveCount(1)
        ->and($panel['absences'])->toHaveCount(1)
        ->and($panel['hours']['taken'])->toBe([1]);
});

it('statutul absenței din panoul zilei: dirigintele decide, profesorul de disciplină NU', function () {
    // Dirigintele clasei — el statutează.
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Diriginte->value);
    $homeroom = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $absence = Absence::factory()->create([
        'student_id' => $a->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'occurred_on' => $azi,
        'is_motivated' => null,
    ]);

    // Profesorul de disciplină: consemnează, dar NU decide — statutul rămâne neatins.
    actingAs($this->profUser);
    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ClassRegister::class)
        ->call('setDayAbsenceStatus', $absence->id, 'motivated');

    expect($absence->fresh()->is_motivated)->toBeNull();

    // Dirigintele: decide pe loc, din același panou.
    actingAs($homeroomUser->fresh());
    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ClassRegister::class)
        ->call('setDayAbsenceStatus', $absence->id, 'motivated');

    expect($absence->fresh()->is_motivated)->toBeTrue();
});

it('panoul zilei validează ținta: elev străin de clasă = panou gol, fără date scurse', function () {
    actingAs($this->profUser);

    // Elev dintr-o ALTĂ clasă — nu e al contextului.
    $foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'IX', 'section' => 'B', 'grade_level' => 9]);
    $foreign = Student::factory()->create();
    Enrollment::factory()->for($foreign)->for($foreignClass)->for($this->year)->create(['left_on' => null]);

    $page = Livewire::test(ClassRegister::class)->instance();
    $panel = $page->dayPanel($foreign->id, Carbon::today()->toDateString());

    expect($panel['student'])->toBeNull()
        ->and($panel['grades'])->toBe([])
        ->and($panel['absences'])->toBe([]);

    // Nici consemnarea nu trece pe un elev străin (enrolled-gard în EnforcesAbsenceScope).
    Livewire::test(ClassRegister::class)->call('addDayAbsence', $foreign->id, Carbon::today()->toDateString(), 1);

    expect(Absence::query()->where('student_id', $foreign->id)->count())->toBe(0);
});

it('panoul zilei arată și nota anulată (gri, fără pârghii) — ziua se citește întreagă', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $grade = Grade::factory()->create([
        'student_id' => $a->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'term_id' => $this->term->id,
        'graded_on' => $azi,
        'value' => 4,
        'annulled_at' => now(),
        'annulment_reason' => 'test',
    ]);

    $panel = Livewire::test(ClassRegister::class)->instance()->dayPanel($a->id, $azi);

    expect($panel['grades'])->toHaveCount(1)
        ->and($panel['grades'][0]['annulled'])->toBeTrue()
        ->and($panel['grades'][0]['can_annul'])->toBeFalse()
        ->and($panel['grades'][0]['can_request'])->toBeFalse();
});

it('nota se adaugă din panoul zilei, pe ZIUA celulei — nu pe data introducerii rapide', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $zi = Carbon::today()->subDays(4)->toDateString();

    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $zi, '8', 'curenta');

    $grade = Grade::query()->where('student_id', $a->id)->first();

    expect($grade)->not->toBeNull()
        ->and($grade->graded_on->toDateString())->toBe($zi)
        ->and((int) $grade->value)->toBe(8)
        ->and((int) $grade->teacher_id)->toBe($this->teacher->id);

    // Valoare imposibilă: refuzată prietenos, nimic scris.
    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $zi, '11', 'curenta');

    expect(Grade::query()->where('student_id', $a->id)->count())->toBe(1);
});

it('nota din panou respectă gărzile: dirigintele-fără-alocare NU poate nota', function () {
    // Diriginte al clasei, dar NU predă disciplina — vede, consemnează absențe, nu notează.
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Diriginte->value);
    $homeroom = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    actingAs($homeroomUser->fresh());

    $a = $this->students->first();

    $panel = Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ClassRegister::class)->instance()
        ->dayPanel($a->id, Carbon::today()->subDays(4)->toDateString());

    expect($panel['can_grade'])->toBeFalse()
        ->and($panel['can_absent'])->toBeTrue();

    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ClassRegister::class)
        ->call('addDayGrade', $a->id, Carbon::today()->subDays(4)->toDateString(), '9', 'curenta');

    expect(Grade::query()->where('student_id', $a->id)->count())->toBe(0);
});
