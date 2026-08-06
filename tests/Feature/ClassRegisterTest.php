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
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Pages\ClassRegister;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Widgets\QuickActions;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
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
use Illuminate\Validation\ValidationException;
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
    // Zilele cu note SUNT pe riglă; rigla mai poartă și zilele de lecție goale (05.08.2026),
    // ca ziua fără nimic scris să poată fi totuși deschisă.
    expect(array_column($page->gradeColumns(), 'iso'))->toContain($zi1, $zi2)
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

    // Ziua TEZEI e pe riglă, cea a notelor curente NU (filtrul taie interogarea), iar clasa
    // rămâne întreagă: golul e informația.
    expect(array_column($instance->gradeColumns(), 'iso'))->toContain(Carbon::today()->subDays(2)->toDateString())
        ->and(array_column($instance->gradeColumns(), 'iso'))->not->toContain(Carbon::today()->subDays(10)->toDateString())
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

    expect(array_column($instance->gradeColumns(), 'iso'))->toContain($zi1)
        ->and(array_column($instance->gradeColumns(), 'iso'))->not->toContain($zi2)
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

    expect(array_column($all->gradeColumns(), 'iso'))->toContain($zi1, $zi2);
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
        // Luna curentă: ziua notei e pe riglă, cea de acum 40 de zile (altă lună) NU.
        ->and(array_column($page->gradeColumns(), 'iso'))->toContain(Carbon::today()->toDateString())
        ->and(array_column($page->gradeColumns(), 'iso'))->not->toContain(Carbon::today()->subDays(40)->toDateString());

    // „Toate" NU mai există în borderou (05.08.2026): pastila lipsește din bară, iar un
    // ‹?mod=toate› venit din URL cade pe implicitul paginii — luna — nu pe tot istoricul.
    $all = Livewire::test(ClassRegister::class)->set('timeMode', 'toate')->instance();

    expect($all->timeMode())->toBe('luna')
        ->and(collect($all->timePills())->pluck('key'))->not->toContain('toate')
        ->and(array_column($all->gradeColumns(), 'iso'))->not->toContain(Carbon::today()->subDays(40)->toDateString());
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

    // Toate cele 30 de zile cu note sunt pe riglă (peste ele se adaugă zilele de lecție goale).
    $isoList = array_column($page->gradeColumns(), 'iso');

    expect(collect(range(0, 29))->every(fn (int $i): bool => in_array(Carbon::today()->subDays(40 - $i)->toDateString(), $isoList, true)))->toBeTrue()
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

it('două ore consecutive ale aceleiași discipline = două absențe distincte, pe ordinale', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::test(ClassRegister::class);

    // Absent la AMBELE ore — două apăsări, două consemnări: Ora 1 (prima pereche), Ora 2 (a doua).
    $page->call('addDayAbsence', $a->id, $azi);
    $page->call('addDayAbsence', $a->id, $azi);

    expect(Absence::query()->where('student_id', $a->id)->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([1, 2])
        // Ambele pleacă FĂRĂ statut: profesorul consemnează, dirigintele decide.
        ->and(Absence::query()->where('student_id', $a->id)->whereNull('is_motivated')->count())->toBe(2);
});

it('ora e ORDINALĂ, nu poziția din orar: chiar cu orar la ora 4, prima consemnare e Ora 1', function () {
    // Decizia 06.08.2026 v2: „Ora nu mai vine din ORAR, ci simbolizează ordinea din ziua dată."
    // Orarul (incomplet, nefiabil) nu mai participă la numerotare.
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    Lesson::query()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'title' => 'x',
        'teacher_name' => 'x',
        'day_of_week' => Carbon::today()->isoWeekday(),
        'lesson_number' => 4,
    ]);

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayAbsence', $a->id, $azi);
    $page->call('addDayAbsence', $a->id, $azi);

    expect(Absence::query()->where('student_id', $a->id)->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([1, 2]);
});

it('ordinea e per (elev, zi): fiecare elev pornește de la Ora 1, altă zi repornește de la 1', function () {
    // Reclamația inițială din 06.08.2026 („prima lipsă primește Ora 4") nu mai are cum să apară:
    // numărătoarea nu curge nici de la un elev la altul, nici de la o zi la alta.
    actingAs($this->profUser);

    [$intai, $alDoilea] = [$this->students->first(), $this->students->get(1)];
    $azi = Carbon::today()->toDateString();
    $ieri = Carbon::today()->subDay()->toDateString();

    $page = Livewire::test(ClassRegister::class);

    // Primul elev umple două ordinale; al doilea vine „curat" — pornește de la Ora 1.
    $page->call('addDayAbsence', $intai->id, $azi);
    $page->call('addDayAbsence', $intai->id, $azi);
    $page->call('addDayAbsence', $alDoilea->id, $azi);

    expect(Absence::query()->where('student_id', $alDoilea->id)->pluck('lesson_number')->all())->toBe([1]);

    // Altă zi a ACELUIAȘI elev: repornește de la Ora 1, nu continuă numărătoarea de azi.
    $page->call('addDayAbsence', $intai->id, $ieri);

    expect(Absence::query()->where('student_id', $intai->id)->orderBy('occurred_on')->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([1, 1, 2]);
});

it('când toate cele opt ore ale zilei sunt consemnate, apăsarea e refuzată', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();
    $page = Livewire::test(ClassRegister::class);

    foreach (range(1, 8) as $ignored) {
        $page->call('addDayAbsence', $a->id, $azi);
    }

    expect(Absence::query()->where('student_id', $a->id)->count())->toBe(8);

    $page->call('addDayAbsence', $a->id, $azi);

    expect(Absence::query()->where('student_id', $a->id)->count())->toBe(8);
});

it('absent la o oră, notă la cealaltă — ziua le poartă pe amândouă, pe ore DIFERITE', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    // Nota orei la care a fost prezent + absența orei la care a lipsit — ambele din panoul zilei.
    // Nota ia automat prima oră liberă (1); absența nu mai încape pe ea → continuă pe 2.
    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta');
    $page->call('addDayAbsence', $a->id, $azi);

    $rows = collect(Livewire::test(ClassRegister::class)->instance()->rows())
        ->keyBy(fn (array $row): int => (int) $row['student']->id);

    expect($rows[$a->id]['gradesByDate'][$azi][0]['value'])->toBe('9')
        ->and(Grade::query()->where('student_id', $a->id)->value('lesson_number'))->toBe(1)
        ->and($rows[$a->id]['absencesByDate'][$azi])->toHaveCount(1)
        ->and($rows[$a->id]['absencesByDate'][$azi][0]['lesson'])->toBe(2);

    // Panoul zilei le arată împreună, iar ocuparea spune că urmează Ora 3.
    $panel = Livewire::test(ClassRegister::class)->instance()->dayPanel($a->id, $azi);

    expect($panel['grades'])->toHaveCount(1)
        ->and($panel['absences'])->toHaveCount(1)
        ->and($panel['busy_count'])->toBe(2)
        ->and($panel['default_hour'])->toBe(3);
});

it('excluderea reciprocă pe ORĂ: peste notă nu intră absență, peste absență nu intră notă', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();
    $page = Livewire::test(ClassRegister::class);

    // Ora 3 primește o notă → absența pe ACEEAȘI oră e refuzată de gardă.
    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta', 3);
    $page->call('addDayAbsence', $a->id, $azi, 3);

    expect(Absence::query()->where('student_id', $a->id)->count())->toBe(0);

    // Ora 5 primește o absență → nota pe ACEEAȘI oră e refuzată; pe altă oră trece.
    $page->call('addDayAbsence', $a->id, $azi, 5);
    $page->call('addDayGrade', $a->id, $azi, '7', 'curenta', 5);

    expect(Grade::query()->where('student_id', $a->id)->count())->toBe(1);

    $page->call('addDayGrade', $a->id, $azi, '7', 'curenta', 6);

    expect(Grade::query()->where('student_id', $a->id)->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([3, 6]);
});

it('o oră poartă O SINGURĂ notă activă; anularea eliberează slotul', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();
    $page = Livewire::test(ClassRegister::class);

    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta', 2);
    // A doua notă pe aceeași oră explicită — refuzată: alegi altă oră.
    $page->call('addDayGrade', $a->id, $azi, '8', 'curenta', 2);

    expect(Grade::query()->where('student_id', $a->id)->count())->toBe(1);

    // Anularea notei eliberează ora: absența (sau altă notă) poate intra pe ea.
    $grade = Grade::query()->where('student_id', $a->id)->firstOrFail();
    $page->call('annulDayGrade', $grade->id, 'greșeală de consemnare');
    $page->call('addDayAbsence', $a->id, $azi, 2);

    expect(Absence::query()->where('student_id', $a->id)->value('lesson_number'))->toBe(2);
});

it('notele fără oră explicită curg pe ordinale: 1, 2, 3 — în ordinea consemnării', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '9', 'curenta');
    $page->call('addDayGrade', $a->id, $azi, '8', 'curenta');
    $page->call('addDayGrade', $a->id, $azi, '7', 'curenta');

    expect(Grade::query()->where('student_id', $a->id)->orderBy('lesson_number')->pluck('lesson_number')->all())
        ->toBe([1, 2, 3]);
});

it('formularul clasic de notă respectă și el slotul: editarea își ignoră propriul rând', function () {
    // Garda e pe server (EnforcesGradeScope), deci acoperă și resursa Note, nu doar panoul.
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $guard = new class
    {
        use EnforcesGradeScope;

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function check(array $data, ?int $ignoreId = null): array
        {
            return $this->enforceGradeScope($data, $ignoreId);
        }
    };

    $base = [
        'student_id' => $a->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'graded_on' => $azi,
        'lesson_number' => 4,
    ];

    $grade = Grade::query()->create([...$guard->check($base), 'type' => 1, 'evaluation_type' => 'curenta', 'value' => 9]);

    // Aceeași oră, alt rând → refuz; propriul rând (ignoreId, cazul editării) → trece.
    expect(fn () => $guard->check($base))->toThrow(ValidationException::class)
        ->and($guard->check($base, (int) $grade->getKey())['lesson_number'])->toBe(4);
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

it('ziua de lecție are coloană chiar dacă e goală — altfel prima notă a zilei n-are cum fi pusă', function () {
    actingAs($this->profUser);

    // Orar: disciplina se predă LUNEA. Nicio notă, nicio absență — ziua trebuie să existe oricum.
    $luni = Carbon::today()->startOfWeek();

    Lesson::query()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'title' => 'x',
        'teacher_name' => 'x',
        'day_of_week' => 1,
        'lesson_number' => 1,
    ]);

    $columns = array_column(Livewire::test(ClassRegister::class)->instance()->gradeColumns(), 'iso');

    expect($columns)->toContain($luni->toDateString())
        // Doar zilele de LECȚIE: marțea nu se predă, deci nu are coloană.
        ->and($columns)->not->toContain($luni->copy()->addDay()->toDateString())
        // Și nimic în VIITOR: o coloană acolo ar fi fundătură (gărzile refuză scrierea).
        ->and(collect($columns)->every(fn (string $iso): bool => $iso <= Carbon::today()->toDateString()))->toBeTrue();
});

it('fără orar, zilele lucrătoare ale perioadei rămân deschise — registrul nu devine inutilizabil', function () {
    actingAs($this->profUser);

    $columns = array_column(
        Livewire::test(ClassRegister::class)->set('timeMode', 'saptamana')->instance()->gradeColumns(),
        'iso',
    );

    $luni = Carbon::today()->startOfWeek();

    expect($columns)->toContain($luni->toDateString())
        // Weekendul nu e zi de școală.
        ->and($columns)->not->toContain($luni->copy()->addDays(5)->toDateString())
        ->and($columns)->not->toContain($luni->copy()->addDays(6)->toDateString());
});

it('o zi cu date rămâne vizibilă chiar dacă nu e zi de lecție (recuperare, corectură)', function () {
    actingAs($this->profUser);

    // Lecțiile sunt lunea; nota cade sâmbătă (recuperare) — ziua ei nu se pierde.
    Lesson::query()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'title' => 'x',
        'teacher_name' => 'x',
        'day_of_week' => 1,
        'lesson_number' => 1,
    ]);

    $sambata = Carbon::today()->startOfWeek()->addDays(5);

    if ($sambata->isFuture()) {
        $sambata = $sambata->subWeek();
    }

    registerGrade($this->students->first(), $sambata->toDateString(), 9);

    $columns = array_column(
        Livewire::test(ClassRegister::class)->set('timeMode', 'luna')->instance()->gradeColumns(),
        'iso',
    );

    expect($columns)->toContain($sambata->toDateString());
});

it('anularea din panou cere motiv, scoate nota din medii și o lasă în istoric', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '4', 'curenta');

    $grade = Grade::query()->sole();

    // Fără motiv: nimic nu se anulează.
    $page->call('annulDayGrade', $grade->id, '   ');

    expect($grade->fresh()->annulled_at)->toBeNull();

    $page->call('annulDayGrade', $grade->id, 'notă pusă din greșeală');

    $grade->refresh();

    expect($grade->annulled_at)->not->toBeNull()
        ->and($grade->annulment_reason)->toBe('notă pusă din greșeală')
        // Rămâne în istoric, dar iese din medii (observerul a recalculat).
        ->and(Grade::query()->count())->toBe(1)
        ->and(TermAverage::query()->where('student_id', $a->id)->whereNull('deleted_at')->count())->toBe(0)
        // Iar în panou apare gri, fără pârghii.
        ->and($page->instance()->dayPanel($a->id, $azi)['grades'][0]['annulled'])->toBeTrue()
        ->and($page->instance()->dayPanel($a->id, $azi)['grades'][0]['can_annul'])->toBeFalse();
});

it('corecția din panou intră în coada de aprobare; a doua cerere e refuzată', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '7', 'curenta');

    $grade = Grade::query()->sole();

    // Câmpuri incomplete: nu se creează nimic.
    $page->call('requestDayCorrection', $grade->id, '9', '');
    $page->call('requestDayCorrection', $grade->id, '', 'motiv');

    expect(GradeCorrection::query()->count())->toBe(0);

    // Valoare în afara scalei: refuzată prietenos.
    $page->call('requestDayCorrection', $grade->id, '11', 'greșeală de tastare');

    expect(GradeCorrection::query()->count())->toBe(0);

    $page->call('requestDayCorrection', $grade->id, '9', 'greșeală de tastare');

    $correction = GradeCorrection::query()->sole();

    expect((int) $correction->new_value)->toBe(9)
        ->and((int) $correction->grade_id)->toBe($grade->id)
        ->and($correction->isPending())->toBeTrue()
        // Nota nu s-a schimbat singură — decizia e a administrației.
        ->and((int) $grade->fresh()->value)->toBe(7)
        // A doua cerere pe aceeași notă: refuzată cât timp una e în așteptare.
        ->and($page->instance()->dayPanel($a->id, $azi)['grades'][0]['can_request'])->toBeFalse();

    $page->call('requestDayCorrection', $grade->id, '10', 'încă o dată');

    expect(GradeCorrection::query()->count())->toBe(1);
});

it('profesorul STRĂIN de pereche nu anulează și nu cere corecție, nici cu apel forțat', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();
    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $azi, '8', 'curenta');

    $grade = Grade::query()->sole();

    // Alt profesor, fără nicio alocare pe (clasă, disciplină).
    $strainUser = User::factory()->create();
    $strainUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $strainUser->id]);

    actingAs($strainUser->fresh());

    $page = Livewire::withQueryParams(['clasa' => (string) $this->class->id])->test(ClassRegister::class);
    $page->call('annulDayGrade', $grade->id, 'nu e treaba mea');
    $page->call('requestDayCorrection', $grade->id, '10', 'nici asta');

    expect($grade->fresh()->annulled_at)->toBeNull()
        ->and(GradeCorrection::query()->count())->toBe(0);
});

// ── Introducerea în MASĂ, doar pe filtrarea „Zi" (06.08.2026) ──────────────────────────────

it('batch pe Zi: notele și absențele întregii clase intră dintr-un singur buton, pe ordinale', function () {
    actingAs($this->profUser);

    [$a, $b, $c] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class);

    expect($page->instance()->canBatchWrite())->toBeTrue();

    // A: notă ȘI absent (a fost la prima oră, a lipsit la a doua); B: doar notă; C: doar absent.
    $page->set('entries.'.$a->id.'.value', '9')
        ->set('entries.'.$a->id.'.absent', true)
        ->set('entries.'.$b->id.'.value', '7')
        ->set('entries.'.$c->id.'.absent', true)
        ->call('saveDayBatch');

    $gradeA = Grade::query()->where('student_id', $a->id)->sole();
    $absenceA = Absence::query()->where('student_id', $a->id)->sole();

    expect((int) $gradeA->value)->toBe(9)
        // Nota ia Ora 1; absența ACELUIAȘI elev cade natural pe Ora 2 — excluderea pe slot ține.
        ->and($gradeA->lesson_number)->toBe(1)
        ->and($absenceA->lesson_number)->toBe(2)
        // Fără statut: profesorul consemnează, dirigintele decide.
        ->and($absenceA->is_motivated)->toBeNull()
        ->and((int) $gradeA->teacher_id)->toBe($this->teacher->id)
        ->and((int) $gradeA->term_id)->toBe($this->term->id)
        ->and(Grade::query()->where('student_id', $b->id)->sole()->lesson_number)->toBe(1)
        ->and(Absence::query()->where('student_id', $c->id)->sole()->lesson_number)->toBe(1)
        // Intrările s-au golit după salvare — sesiunea următoare pornește curată.
        ->and($page->get('entries'))->toBe([]);
});

it('batch-ul e ATOMIC: un singur rând invalid anulează tot, cu eroarea pe rândul lui', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class)
        ->set('entries.'.$a->id.'.value', '9')
        ->set('entries.'.$b->id.'.value', '11')
        ->call('saveDayBatch')
        ->assertHasErrors(['entries.'.$b->id.'.value']);

    // Nici rândul VALID nu s-a salvat — rollback total, nu jumătăți de clasă.
    expect(Grade::query()->count())->toBe(0);
});

it('salvarea în masă refuză orice alt mod decât Zi — iar coloanele nici nu se randează acolo', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    // Pe „Luna": fără coloane de introducere, fără buton, iar apelul forțat nu scrie nimic.
    $luna = Livewire::withQueryParams(['mod' => 'luna'])->test(ClassRegister::class);

    expect($luna->instance()->canBatchWrite())->toBeFalse();

    $luna->assertDontSeeHtml('wire:model="entries.')
        ->assertDontSeeHtml('saveDayBatch');

    $luna->set('entries.'.$a->id.'.value', '9')->call('saveDayBatch');

    expect(Grade::query()->count())->toBe(0);

    // Pe „Zi": ambele coloane și butonul există.
    Livewire::withQueryParams(['mod' => 'zi', 'ref' => Carbon::today()->toDateString()])
        ->test(ClassRegister::class)
        ->assertSeeHtml('wire:model="entries.')
        ->assertSeeHtml('saveDayBatch');
});

it('elevii străini strecurați în payload se ignoră; fără nimic valid — nimic de salvat', function () {
    actingAs($this->profUser);

    $other = SchoolClass::factory()->for($this->year)->create(['name' => 'VI', 'section' => 'C', 'grade_level' => 6]);
    $foreign = Student::factory()->create();
    Enrollment::factory()->for($foreign)->for($other)->for($this->year)->create(['left_on' => null]);

    Livewire::withQueryParams(['mod' => 'zi', 'ref' => Carbon::today()->toDateString()])
        ->test(ClassRegister::class)
        ->set('entries.'.$foreign->id.'.value', '10')
        ->call('saveDayBatch');

    expect(Grade::query()->count())->toBe(0);
});

it('dirigintele fără alocare: absențele din batch trec, o notă strecurată blochează tot', function () {
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Profesor->value);
    $homeroom = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    actingAs($homeroomUser->fresh());

    [$a, $b] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    $params = ['mod' => 'zi', 'ref' => $azi, 'disciplina' => (string) $this->subject->id];

    // Doar absențe: trec (dirigintele consemnează absențe la orice disciplină a clasei lui).
    Livewire::withQueryParams($params)->test(ClassRegister::class)
        ->set('entries.'.$a->id.'.absent', true)
        ->call('saveDayBatch');

    expect(Absence::query()->where('student_id', $a->id)->count())->toBe(1)
        ->and(Absence::query()->where('student_id', $a->id)->sole()->lesson_number)->toBe(1);

    // O valoare de notă venită totuși (coloana nu se randează pentru el) = payload manipulat →
    // refuz explicit cu rollback total, inclusiv absența din același batch.
    Livewire::withQueryParams($params)->test(ClassRegister::class)
        ->set('entries.'.$b->id.'.value', '9')
        ->set('entries.'.$b->id.'.absent', true)
        ->call('saveDayBatch')
        ->assertHasErrors(['entries.'.$b->id.'.value']);

    expect(Grade::query()->count())->toBe(0)
        ->and(Absence::query()->where('student_id', $b->id)->count())->toBe(0);
});

it('disciplina pe calificativ: batch-ul acceptă simbolul scalei și refuză cifrele', function () {
    $this->subject->update(['grading_type' => 'c']);

    actingAs($this->profUser);

    [$a, $b] = $this->students->all();
    $azi = Carbon::today()->toDateString();

    // „fb" se normalizează la FB; tipul e forțat „curentă" la calificative.
    Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class)
        ->set('entries.'.$a->id.'.value', 'fb')
        ->set('batchType', 'teza')
        ->call('saveDayBatch');

    $grade = Grade::query()->where('student_id', $a->id)->sole();

    expect($grade->calificativ)->toBe('FB')
        ->and($grade->value)->toBeNull()
        ->and($grade->evaluation_type)->toBe(EvaluationType::Curenta);

    // O cifră pe disciplină cu calificativ — refuz cu rollback.
    Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class)
        ->set('entries.'.$b->id.'.value', '9')
        ->call('saveDayBatch')
        ->assertHasErrors(['entries.'.$b->id.'.value']);

    expect(Grade::query()->where('student_id', $b->id)->count())->toBe(0);
});

it('batch-ul continuă ordinalele zilei: peste o absență existentă la Ora 1, nota ia Ora 2', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    Absence::factory()->create([
        'student_id' => $a->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'occurred_on' => $azi,
        'lesson_number' => 1,
        'is_motivated' => null,
    ]);

    Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class)
        ->set('entries.'.$a->id.'.value', '8')
        ->call('saveDayBatch');

    expect(Grade::query()->where('student_id', $a->id)->sole()->lesson_number)->toBe(2);
});

// ── Corectarea OREI unei consemnări (07.08.2026) ───────────────────────────────────────────

it('nota și absența își SCHIMBĂ locurile când ora aleasă e ocupată de cealaltă', function () {
    // Scenariul raportat: batch-ul pune nota pe Ora 1 și absența pe Ora 2 (convenția ordinii de
    // procesare), dar în clasă a fost invers — a lipsit la prima oră, a răspuns la a doua.
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class);
    $page->set('entries.'.$a->id.'.value', '9')
        ->set('entries.'.$a->id.'.absent', true)
        ->call('saveDayBatch');

    $grade = Grade::query()->where('student_id', $a->id)->sole();
    $absence = Absence::query()->where('student_id', $a->id)->sole();

    expect($grade->lesson_number)->toBe(1)->and($absence->lesson_number)->toBe(2);

    // O singură acțiune: nota trece pe Ora 2, iar absența primește ora eliberată — nu rămâne
    // niciun moment în care două consemnări împart aceeași oră.
    $page->call('moveDayGradeHour', $grade->id, 2);

    expect($grade->fresh()->lesson_number)->toBe(2)
        ->and($absence->fresh()->lesson_number)->toBe(1);

    // Și invers, pornind de la absență.
    $page->call('moveDayAbsenceHour', $absence->id, 2);

    expect($absence->fresh()->lesson_number)->toBe(2)
        ->and($grade->fresh()->lesson_number)->toBe(1);
});

it('mutarea pe o oră LIBERĂ e simplă, iar ora eliberată redevine disponibilă', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $page = Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class);
    $page->call('addDayGrade', $a->id, $azi, '8', 'curenta');

    $grade = Grade::query()->where('student_id', $a->id)->sole();

    $page->call('moveDayGradeHour', $grade->id, 5);

    expect($grade->fresh()->lesson_number)->toBe(5)
        // Ora 1 s-a eliberat: următoarea consemnare o ia pe ea.
        ->and($page->instance()->dayPanel($a->id, $azi)['default_hour'])->toBe(1);
});

it('ora se corectează doar de cine are dreptul, și doar între 1 și 8', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $azi, '8', 'curenta');
    $grade = Grade::query()->sole();

    // Ordinal în afara scalei — refuzat.
    Livewire::withQueryParams(['mod' => 'zi', 'ref' => $azi])->test(ClassRegister::class)
        ->call('moveDayGradeHour', $grade->id, 9);

    expect($grade->fresh()->lesson_number)->toBe(1);

    // Profesor STRĂIN de pereche — refuzat chiar cu apel forțat.
    $strainUser = User::factory()->create();
    $strainUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $strainUser->id]);

    actingAs($strainUser->fresh());

    Livewire::withQueryParams(['clasa' => (string) $this->class->id])->test(ClassRegister::class)
        ->call('moveDayGradeHour', $grade->id, 3);

    expect($grade->fresh()->lesson_number)->toBe(1);
});

it('dirigintele fără alocare mută absențele, dar nu ia locul unei note', function () {
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Profesor->value);
    $homeroom = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    $a = $this->students->first();
    $azi = Carbon::today()->toDateString();

    // Nota o pune titularul disciplinei, pe Ora 1.
    actingAs($this->profUser);
    Livewire::test(ClassRegister::class)->call('addDayGrade', $a->id, $azi, '9', 'curenta');

    // Dirigintele consemnează absența (Ora 2) și o poate muta pe o oră liberă...
    actingAs($homeroomUser->fresh());

    $params = ['mod' => 'zi', 'ref' => $azi, 'disciplina' => (string) $this->subject->id];
    $page = Livewire::withQueryParams($params)->test(ClassRegister::class);
    $page->call('addDayAbsence', $a->id, $azi);

    $absence = Absence::query()->where('student_id', $a->id)->sole();

    expect($absence->lesson_number)->toBe(2);

    $page->call('moveDayAbsenceHour', $absence->id, 4);

    expect($absence->fresh()->lesson_number)->toBe(4);

    // ...dar NU peste ora notei: schimbul ar muta nota, la care nu are drept.
    $page->call('moveDayAbsenceHour', $absence->id, 1);

    expect($absence->fresh()->lesson_number)->toBe(4)
        ->and(Grade::query()->sole()->lesson_number)->toBe(1);
});

it('pe o zi din VIITOR introducerea în masă nu există și nu scrie', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $maine = Carbon::tomorrow()->toDateString();

    $page = Livewire::withQueryParams(['mod' => 'zi', 'ref' => $maine])->test(ClassRegister::class);

    expect($page->instance()->canBatchWrite())->toBeFalse();

    $page->set('entries.'.$a->id.'.value', '9')->call('saveDayBatch');

    expect(Grade::query()->count())->toBe(0);
});
