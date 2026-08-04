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

it('salvează un batch întreg — note și absențe pe mai mulți elevi — dintr-o singură acțiune', function () {
    actingAs($this->profUser);

    [$a, $b, $c] = $this->students->all();

    Livewire::test(ClassRegister::class)
        ->set('entries', [
            (string) $a->id => ['value' => '9'],
            (string) $b->id => ['value' => '7', 'absence' => null],
            (string) $c->id => ['value' => '', 'absence' => ClassRegister::ABSENCE_MARKED],
        ])
        ->call('saveEntries')
        ->assertHasNoErrors();

    // Notele: autorul e profesorul logat, semestrul derivat din dată, valorile exacte.
    $gradeA = Grade::query()->where('student_id', $a->id)->sole();

    expect(Grade::query()->count())->toBe(2)
        ->and((int) $gradeA->teacher_id)->toBe($this->teacher->id)
        ->and((int) $gradeA->term_id)->toBe($this->term->id)
        ->and((float) $gradeA->value)->toBe(9.0)
        // Absența: FĂRĂ statut (profesorul consemnează, dirigintele decide), pe aceeași disciplină.
        ->and(Absence::query()->count())->toBe(1)
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

it('o valoare invalidă anulează TOT batch-ul — nimic parțial', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();

    Livewire::test(ClassRegister::class)
        ->set('entries', [
            (string) $a->id => ['value' => '10'],
            (string) $b->id => ['value' => '11'],
        ])
        ->call('saveEntries')
        ->assertHasErrors(['entries.'.$b->id]);

    expect(Grade::query()->count())->toBe(0);
});

it('absența duplicat (același elev, aceeași zi, aceeași disciplină) blochează batch-ul', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    Absence::query()->create([
        'student_id' => $a->id,
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->teacher->id,
        'occurred_on' => Carbon::today(),
        'is_motivated' => false,
    ]);

    Livewire::test(ClassRegister::class)
        ->set('entries', [(string) $a->id => ['absence' => ClassRegister::ABSENCE_MARKED]])
        ->call('saveEntries')
        ->assertHasErrors(['entries.'.$a->id]);

    expect(Absence::query()->count())->toBe(1);
});

it('nu vede și nu salvează pe clasa altui profesor', function () {
    $other = SchoolClass::factory()->for($this->year)->create(['name' => 'VI', 'section' => 'B', 'grade_level' => 6]);
    $stranger = Student::factory()->create();
    Enrollment::factory()->for($stranger)->for($other)->for($this->year)->create(['left_on' => null]);

    actingAs($this->profUser);

    $component = Livewire::withQueryParams(['clasa' => (string) $other->id])->test(ClassRegister::class);

    // Parametrul străin cade pe prima clasă PERMISĂ — nu pe clasa cerută.
    expect($component->instance()->activeClass()?->id)->toBe($this->class->id);

    // Iar un id de elev străin strecurat în payload e ignorat (nu e printre rândurile vizibile).
    $component
        ->set('entries', [(string) $stranger->id => ['value' => '10']])
        ->call('saveEntries');

    expect(Grade::query()->where('student_id', $stranger->id)->count())->toBe(0);
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

    $component
        ->set('entries', [(string) $a->id => ['value' => '9', 'absence' => ClassRegister::ABSENCE_MARKED]])
        ->call('saveEntries')
        ->assertHasNoErrors();

    // Nota NU s-a creat (nu are dreptul); absența DA, sub numele lui — FĂRĂ statut, ca orice
    // consemnare din borderou: chiar și dirigintele o statutează apoi din secțiunea Absențe.
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

it('disciplina pe calificativ acceptă calificativul și refuză peste 10 caractere', function () {
    $this->subject->update(['grading_type' => 'c']);
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();

    Livewire::test(ClassRegister::class)
        ->set('entries', [(string) $a->id => ['value' => 'FB']])
        ->call('saveEntries')
        ->assertHasNoErrors();

    $grade = Grade::query()->where('student_id', $a->id)->sole();

    expect($grade->calificativ)->toBe('FB')
        ->and($grade->value)->toBeNull();

    Livewire::test(ClassRegister::class)
        ->set('entries', [(string) $b->id => ['value' => 'PREA-LUNG-XX']])
        ->call('saveEntries')
        ->assertHasErrors(['entries.'.$b->id]);
});

it('nota nu poate fi în viitor — gardă moștenită din traitul formularului', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    Livewire::test(ClassRegister::class)
        ->set('entryDate', Carbon::tomorrow()->toDateString())
        ->set('entries', [(string) $a->id => ['value' => '9']])
        ->call('saveEntries')
        ->assertHasErrors(['entries.'.$a->id]);

    expect(Grade::query()->count())->toBe(0);
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

it('pagina se randează cu elevii, borderoul și butonul de salvare', function () {
    actingAs($this->profUser);

    Livewire::test(ClassRegister::class)
        ->assertOk()
        ->assertSee('Albu')
        ->assertSee('Zamfir')
        ->assertSee(trans('panel.class_register.save_all'))
        ->assertSee(trans('panel.class_register.entry_hint'));
});

// ── Corecturile din 2026-07-30 (data = sursă unică) ────────────────────────────────────────────

it('semestrul afișat urmează DATA, nu un selector separat', function () {
    // Semestrul I al aceluiași an, cu note în el — trebuie să apară doar când data cade acolo.
    $first = Term::factory()->for($this->year)->create([
        'number' => 2,
        'name' => 'Semestrul I',
        'is_current' => false,
        'starts_on' => Carbon::today()->subMonths(6),
        'ends_on' => Carbon::today()->subMonths(3),
    ]);

    actingAs($this->profUser);

    $inSecond = Livewire::test(ClassRegister::class)->instance();
    expect($inSecond->activeTerm()?->id)->toBe($this->term->id);

    // Mutăm data în semestrul I → borderoul se mută cu ea, fără niciun alt control.
    $inFirst = Livewire::test(ClassRegister::class)
        ->set('entryDate', Carbon::today()->subMonths(4)->toDateString())
        ->instance();

    expect($inFirst->activeTerm()?->id)->toBe($first->id);
});

// ── Absența: UN marcaj, fără statut — profesorul consemnează, dirigintele decide (04.08.2026) ──

it('toate absențele din batch pleacă fără statut — statutul e al dirigintelui', function () {
    actingAs($this->profUser);

    [$a, $b] = $this->students->all();

    Livewire::test(ClassRegister::class)
        ->set('entries', [
            (string) $a->id => ['absence' => ClassRegister::ABSENCE_MARKED],
            (string) $b->id => ['absence' => ClassRegister::ABSENCE_MARKED],
        ])
        ->call('saveEntries')
        ->assertHasNoErrors();

    // Profesorul nu mai alege Mot./Nem.: ambele rânduri așteaptă decizia dirigintelui.
    expect(Absence::query()->where('student_id', $a->id)->sole()->is_motivated)->toBeNull()
        ->and(Absence::query()->where('student_id', $b->id)->sole()->is_motivated)->toBeNull();
});

it('comutatorul de absență e un simplu marcaj: click activează, click anulează', function () {
    actingAs($this->profUser);

    $a = $this->students->first();
    $component = Livewire::test(ClassRegister::class);

    // Marcare directă…
    $component->call('toggleAbsence', $a->id);
    expect($component->get('entries')[$a->id]['absence'])->toBe(ClassRegister::ABSENCE_MARKED);

    // …iar al doilea click o anulează: elevul redevine prezent.
    $component->call('toggleAbsence', $a->id);
    expect($component->get('entries')[$a->id]['absence'])->toBeNull();

    $component->call('saveEntries');
    expect(Absence::query()->count())->toBe(0);
});

it('o stare de absență necunoscută din payload nu creează nimic', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    Livewire::test(ClassRegister::class)
        ->set('entries', [(string) $a->id => ['absence' => 'oricum-altcumva']])
        ->call('saveEntries');

    expect(Absence::query()->count())->toBe(0);
});

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

it('schimbarea datei golește intrările începute — nu se scriu pe alt semestru', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    $component = Livewire::test(ClassRegister::class)
        ->set('entries', [(string) $a->id => ['value' => '9']])
        ->set('entryDate', Carbon::today()->subDay()->toDateString());

    expect($component->get('entries'))->toBe([]);
});

it('în vacanță data implicită cade în semestru, nu pe ziua de azi', function () {
    // Anul s-a încheiat luna trecută: „azi" nu mai aparține niciunui semestru. Fără corecție,
    // fiecare salvare pica pe garda de rollover, deși profesorul nu greșise nimic.
    // Query builder: gărzile de model pe intervale ar refuza mutarea în trecut a unui an care
    // începe mai devreme — aici ne interesează doar starea rezultată, nu calea de configurare.
    DB::table('terms')->where('id', $this->term->id)->update([
        'starts_on' => Carbon::today()->subMonths(5)->toDateString(),
        'ends_on' => Carbon::today()->subMonth()->toDateString(),
    ]);
    DB::table('academic_years')->where('id', $this->year->id)->update([
        'starts_on' => Carbon::today()->subMonths(11)->toDateString(),
        'ends_on' => Carbon::today()->subMonth()->toDateString(),
    ]);

    actingAs($this->profUser);

    $page = Livewire::test(ClassRegister::class);

    expect($page->get('entryDate'))->toBe(Carbon::today()->subMonth()->toDateString());

    // Iar cu acea dată, salvarea chiar trece.
    $a = $this->students->first();

    $page->set('entries', [(string) $a->id => ['value' => '9']])
        ->call('saveEntries')
        ->assertHasNoErrors();

    expect(Grade::query()->count())->toBe(1);
});

it('teza intră cu tipul ales și apare evidențiată în rând', function () {
    actingAs($this->profUser);

    $a = $this->students->first();

    Livewire::test(ClassRegister::class)
        ->set('entryType', EvaluationType::Teza->value)
        ->set('entries', [(string) $a->id => ['value' => '8']])
        ->call('saveEntries')
        ->assertHasNoErrors();

    expect(Grade::query()->sole()->evaluation_type)->toBe(EvaluationType::Teza);

    $rows = Livewire::test(ClassRegister::class)->instance()->rows();
    $rowA = collect($rows)->first(fn (array $row): bool => $row['student']->id === $a->id);

    expect($rowA['grades'][0]['weighted'])->toBeTrue();
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
    $second = Subject::factory()->create(['name' => 'Biologie', 'grading_type' => 'n']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $second->id,
    ]);

    actingAs($this->profUser);

    $student = $this->students->first();

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'disciplina' => (string) $second->id])
        ->test(ClassRegister::class)
        ->set('entries', [(string) $student->id => ['value' => '9']])
        ->call('saveEntries')
        ->assertHasNoErrors();

    expect(Grade::query()->sole()->subject_id)->toBe($second->id);
});
