<?php

/**
 * HARTA NOTELOR (cerința beneficiarului, 05.08.2026 — sora hărții absențelor, pe unitatea NOTĂ).
 *
 * Testele fixează promisiunile proprii notelor:
 *  - VALOAREA e eticheta (întreagă, fără zecimalele castului), pragul dă culoarea, sumativa
 *    accentul; notele ANULATE nu există pe hartă (nu contează la medii);
 *  - disciplina e MEREU aleasă (fără „Toate"): la intrarea în clasă se alege automat primul
 *    chip, iar coloana Total arată Note / Sub 5 / MEDIA oficială din term_averages;
 *  - pastila poartă DOAR pârghiile privitorului — nimic care să ducă în 403 (lecția absențelor);
 *  - acțiunile din hartă trec prin ACELEAȘI gărzi ca tabelul, pe server, iar efectele curg mai
 *    departe: anularea recalculează mediile, corecția intră în coada de aprobare.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\ListGrades;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->term = Term::factory()->for($this->year, 'academicYear')->create([
        'number' => 2,
        'starts_on' => Carbon::today()->subMonths(3)->toDateString(),
        'ends_on' => Carbon::today()->addMonths(2)->toDateString(),
        'is_current' => true,
    ]);

    // Treaptă de GIMNAZIU: eticheta sumativei e ESS — fixăm și partea de ciclu.
    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => 'B', 'grade_level' => 7]);
    $this->subject = Subject::factory()->create(['name' => 'Chimie', 'abbreviation' => 'Ch']);
    $this->otherSubject = Subject::factory()->create(['name' => 'Istorie']);

    // Titularul disciplinei — el are pârghiile perechii lui.
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $this->subjectTeacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $this->teacherUser = $teacherUser->fresh();

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->subjectTeacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // Al doilea titular (Istorie) — notele lui NU sunt ale primului.
    $this->colleague = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->colleague->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->otherSubject->id,
    ]);

    // Dirigintele clasei — NU predă nimic aici: poate doar SOLICITA corecții.
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Diriginte->value);
    $this->homeroomTeacher = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $this->homeroomTeacher->id]);
    $this->homeroomUser = $homeroomUser->fresh();

    // Administrația (directorul) — editare directă + anulare, oriunde.
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->director = $director->fresh();

    $this->students = collect(['Vulpe', 'Barbu', 'Neagu'])->map(function (string $name) {
        $student = Student::factory()->create(['last_name' => $name, 'first_name' => 'Elev']);
        Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create([
            'enrolled_on' => Carbon::today()->subMonths(3),
            'left_on' => null,
        ]);

        return $student;
    });
});

/** Notă în clasa de test — implicit: disciplina titularului, curentă, activă. */
function gradeMapRecord(object $ctx, Student $student, string $date, int $value, string $type = 'curenta', ?Subject $subject = null, bool $annulled = false): Grade
{
    $subject ??= $ctx->subject;

    return Grade::query()->create([
        'student_id' => $student->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $subject->is($ctx->subject) ? $ctx->subjectTeacher->id : $ctx->colleague->id,
        'term_id' => $ctx->term->id,
        'graded_on' => $date,
        'type' => 1,
        'evaluation_type' => $type,
        'value' => $value,
        'annulled_at' => $annulled ? now() : null,
        'annulment_reason' => $annulled ? 'test' : null,
    ]);
}

it('harta e vederea implicită în context de clasă, iar lista rămâne la un click', function () {
    actingAs($this->homeroomUser);

    expect(Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListGrades::class)->instance()->showsGradeMap())->toBeTrue()
        ->and(Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'forma' => 'lista'])
            ->test(ListGrades::class)->instance()->showsGradeMap())->toBeFalse()
        ->and(Livewire::test(ListGrades::class)->instance()->showsGradeMap())->toBeFalse();
});

it('valoarea e eticheta (întreagă), pragul și sumativa dau culorile, totalurile numără corect', function () {
    [$vulpe, $barbu] = $this->students->all();
    $zi = Carbon::today()->subDays(3)->toDateString();

    gradeMapRecord($this, $vulpe, $zi, 9);
    gradeMapRecord($this, $vulpe, $zi, 4);            // sub prag, aceeași disciplină, aceeași zi
    gradeMapRecord($this, $vulpe, $zi, 8, 'teza');    // sumativă (ESS la gimnaziu)
    // Nota lui Barbu la ISTORIE: cu disciplina auto-aleasă (Chimie, prima alfabetic), ea NU e
    // în hartă — dovada că harta e mereu pe O disciplină.
    gradeMapRecord($this, $barbu, $zi, 10, 'curenta', $this->otherSubject);
    gradeMapRecord($this, $barbu, $zi, 2, annulled: true); // ANULATĂ — nu există pe hartă

    actingAs($this->director);

    $component = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class);

    // Fără ?disciplina, contextul se auto-alege pe PRIMUL chip (alfabetic: Chimie).
    expect($component->instance()->catalogSubjectIdInContext())->toBe($this->subject->id);

    $map = $component->instance()->gradeMap();

    $rows = collect($map['rows'])->keyBy(fn (array $row): int => (int) $row['student']->id);
    $vulpeCell = $rows[$vulpe->id]['cells'][$zi];

    // Toată clasa pe rânduri, alfabetic — inclusiv elevul fără note.
    expect(array_map(fn (array $r): string => (string) $r['student']->last_name, $map['rows']))
        ->toBe(['Barbu', 'Neagu', 'Vulpe'])
        // Două pastile la Vulpe: duplicatele aceleiași discipline rămân separate, iar sumativa
        // stă în filtrul ei (implicit se văd notele CURENTE — vezi testul filtrului de tip).
        ->and($vulpeCell)->toHaveCount(2)
        // Eticheta e ÎNTREAGĂ — nu zecimalele castului (9.00).
        ->and(array_column($vulpeCell, 'label'))->toBe(['9', '4'])
        ->and(array_column($vulpeCell, 'below'))->toBe([false, true])
        ->and(array_column($vulpeCell, 'summative'))->toBe([false, false])
        ->and($rows[$vulpe->id]['totals']['total'])->toBe(2)
        ->and($rows[$vulpe->id]['totals']['below'])->toBe(1)
        // MEDIA din Total = cea OFICIALĂ din term_averages (motorul cu ponderare), nu un calcul
        // propriu al hărții — egalitate pe sursă.
        ->and($rows[$vulpe->id]['totals']['average'])->toBe((string) TermAverage::query()
        ->where('student_id', $vulpe->id)->where('subject_id', $this->subject->id)
        ->where('term_id', $this->term->id)->value('value'))
        // Istoria lui Barbu NU e pe harta de Chimie; anulata de Chimie — nici ea. Rând gol, fără medie.
        ->and($rows[$barbu->id]['totals']['total'])->toBe(0)
        ->and($rows[$barbu->id]['totals']['average'])->toBeNull()
        ->and($rows[$barbu->id]['cells'])->toBe([]);
});

it('filtrul de tip arată UN singur fel de notă odată, ca în Catalogul Electronic', function () {
    // Cerința beneficiarului (05.08.2026): pe hartă, ca în borderou, se alege tipul — altfel
    // „colonița" unei evaluări nu se poate urmări printre curente.
    [$vulpe] = $this->students->all();
    $zi = Carbon::today()->subDays(3)->toDateString();
    $ziSumativa = Carbon::today()->subDays(2)->toDateString();

    gradeMapRecord($this, $vulpe, $zi, 9);
    gradeMapRecord($this, $vulpe, $ziSumativa, 8, 'teza');

    actingAs($this->director);

    $harta = fn (array $params): array => Livewire::withQueryParams(
        ['clasa' => (string) $this->class->id, 'mod' => 'toate'] + $params,
    )->test(ListGrades::class)->instance()->gradeMap();

    // IMPLICIT: doar curentele — ziua sumativei nici nu apare ca DE COLOANĂ.
    $curente = $harta([]);

    expect(array_column($curente['days'], 'iso'))->toBe([$zi])
        ->and($curente['rows'][2]['cells'][$zi])->toHaveCount(1)
        ->and($curente['rows'][2]['totals']['total'])->toBe(1);

    // Pe „teză": rămâne DOAR sumativa, cu eticheta ciclului (gimnaziu → ESS).
    $sumative = $harta(['tip_note' => 'teza']);

    expect(array_column($sumative['days'], 'iso'))->toBe([$ziSumativa])
        ->and($sumative['rows'][2]['cells'][$ziSumativa][0]['summative'])->toBeTrue()
        ->and($sumative['rows'][2]['cells'][$ziSumativa][0]['type_label'])->toContain('ESS')
        // Media din Total rămâne cea OFICIALĂ (toate tipurile, cu ponderare): filtrul taie ce se
        // VEDE, nu cum se calculează media.
        ->and($sumative['rows'][2]['totals']['average'])->toBe($curente['rows'][2]['totals']['average']);

    // Un tip inventat din URL cade pe implicit, nu produce o hartă goală fără explicație.
    $component = Livewire::withQueryParams([
        'clasa' => (string) $this->class->id, 'mod' => 'toate', 'tip_note' => 'teza-de-anul-trecut',
    ])->test(ListGrades::class);

    expect($component->instance()->activeGradeType()->value)->toBe(ListGrades::DEFAULT_GRADE_TYPE)
        ->and(array_column($component->instance()->gradeMap()['days'], 'iso'))->toBe([$zi])
        // Opțiunile sunt exact cele trei tipuri, fără „toate" — ca în borderou.
        ->and(array_keys($component->instance()->gradeTypeOptions()))->toBe(['curenta', 'esi', 'teza']);
});

it('schimbarea tipului reîmprospătează harta, nu servește setul memoizat', function () {
    // Harta se memoizează per-request (blade-ul o citește de mai multe ori); fără invalidare la
    // schimbarea filtrului, prima alegere ar fi rămas pe ecran.
    [$vulpe] = $this->students->all();
    gradeMapRecord($this, $vulpe, Carbon::today()->subDays(3)->toDateString(), 9);
    gradeMapRecord($this, $vulpe, Carbon::today()->subDays(2)->toDateString(), 7, 'esi');

    actingAs($this->director);

    $component = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class);

    expect($component->instance()->gradeMap()['rows'][2]['totals']['total'])->toBe(1);

    $component->set('gradeTypeFilter', 'esi');

    expect($component->instance()->activeGradeType()->value)->toBe('esi')
        ->and($component->instance()->gradeMap()['rows'][2]['cells'])->toHaveCount(1)
        ->and($component->instance()->gradeMap()['rows'][2]['totals']['total'])->toBe(1);
});

it('pastila poartă DOAR pârghiile privitorului — nimic spre 403', function () {
    $zi = Carbon::today()->subDays(2)->toDateString();
    $aTitularului = gradeMapRecord($this, $this->students->first(), $zi, 7);
    $aColegului = gradeMapRecord($this, $this->students->first(), $zi, 6, 'curenta', $this->otherSubject);

    $chipsFor = function (User $user) use ($zi): array {
        actingAs($user);

        $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
            ->test(ListGrades::class)
            ->instance()
            ->gradeMap();

        return collect($map['rows'])
            ->firstWhere('student.id', $this->students->first()->id)['cells'][$zi] ?? [];
    };

    // ADMINISTRAȚIA: editare directă + anulare, fără „solicită corecție" (ea aprobă, nu cere).
    $adminChips = collect($chipsFor($this->director))->keyBy('id');

    expect($adminChips[$aTitularului->id]['edit_url'])->not->toBeNull()
        ->and($adminChips[$aTitularului->id]['can_annul'])->toBeTrue()
        ->and($adminChips[$aTitularului->id]['can_request'])->toBeFalse();

    // TITULARUL: pe perechea lui — corecție + anulare; nota colegului NICI NU EXISTĂ în harta lui.
    $teacherChips = collect($chipsFor($this->teacherUser))->keyBy('id');

    expect($teacherChips->has($aColegului->id))->toBeFalse()
        ->and($teacherChips[$aTitularului->id]['edit_url'])->toBeNull()
        ->and($teacherChips[$aTitularului->id]['can_annul'])->toBeTrue()
        ->and($teacherChips[$aTitularului->id]['can_request'])->toBeTrue();

    // DIRIGINTELE (nu predă nimic): pe chip-ul Istoriei vede nota colegului — doar corecție.
    actingAs($this->homeroomUser);

    $istorie = collect(Livewire::withQueryParams([
        'clasa' => (string) $this->class->id,
        'disciplina' => (string) $this->otherSubject->id,
        'mod' => 'toate',
    ])->test(ListGrades::class)->instance()->gradeMap()['rows'])
        ->firstWhere('student.id', $this->students->first()->id)['cells'][$zi] ?? [];

    $homeroomChips = collect($istorie)->keyBy('id');

    expect($homeroomChips)->toHaveCount(1)
        ->and($homeroomChips[$aColegului->id]['edit_url'])->toBeNull()
        ->and($homeroomChips[$aColegului->id]['can_annul'])->toBeFalse()
        ->and($homeroomChips[$aColegului->id]['can_request'])->toBeTrue();
});

it('anularea din hartă scoate nota din medii — observerul recalculează', function () {
    $student = $this->students->first();
    $zi = Carbon::today()->subDays(5)->toDateString();

    gradeMapRecord($this, $student, $zi, 10);
    $slaba = gradeMapRecord($this, $student, Carbon::today()->subDays(4)->toDateString(), 6);

    $inainte = TermAverage::query()
        ->where('student_id', $student->id)
        ->where('subject_id', $this->subject->id)
        ->where('term_id', $this->term->id)
        ->value('value');

    expect((float) $inainte)->toBe(8.0);

    actingAs($this->teacherUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('annulDayGrade', $slaba->id, 'greșeală de introducere');

    $dupa = TermAverage::query()
        ->where('student_id', $student->id)
        ->where('subject_id', $this->subject->id)
        ->where('term_id', $this->term->id)
        ->value('value');

    expect($slaba->fresh()->isAnnulled())->toBeTrue()
        ->and((float) $dupa)->toBe(10.0);
});

it('corecția din hartă intră în coada de aprobare, iar a doua cerere e refuzată', function () {
    $grade = gradeMapRecord($this, $this->students->first(), Carbon::today()->subDays(2)->toDateString(), 5);

    actingAs($this->homeroomUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('requestDayCorrection', $grade->id, '6', 'media reală e alta');

    expect(GradeCorrection::query()->where('grade_id', $grade->id)->count())->toBe(1)
        ->and($grade->fresh()->hasPendingCorrection())->toBeTrue()
        // Nota NU s-a schimbat — cererea așteaptă aprobarea administrației.
        ->and((int) $grade->fresh()->value)->toBe(5);

    // A doua cerere, cât prima așteaptă: refuzată pe server (gardă + invariant în observer).
    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('requestDayCorrection', $grade->id, '7', 'a doua');

    expect(GradeCorrection::query()->where('grade_id', $grade->id)->count())->toBe(1);
});

it('un profesor STRĂIN de pereche nu poate anula nici cu apel forțat', function () {
    $grade = gradeMapRecord($this, $this->students->first(), Carbon::today()->subDays(2)->toDateString(), 9, 'curenta', $this->otherSubject);

    // Titularul de CHIMIE forțează anularea notei de ISTORIE (nu e perechea lui): interogarea
    // scoped nici nu-i întoarce nota → refuz, nota neatinsă.
    actingAs($this->teacherUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('annulDayGrade', $grade->id, 'încercare');

    expect($grade->fresh()->isAnnulled())->toBeFalse();
});

// ── Scrierea din hartă (05.08.2026): panoul doar-note, sincron cu Catalogul Electronic ────────

it('nota se adaugă din harta Note, pe ziua celulei — și harta se redesenează cu ea', function () {
    actingAs($this->teacherUser);

    $student = $this->students->first();
    $azi = Carbon::today()->toDateString();

    $component = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class);

    // Harta e citită (memoizată), APOI se scrie: fără invalidare, nota n-ar apărea.
    expect(collect($component->instance()->gradeMap()['rows'])
        ->firstWhere(fn (array $r): bool => $r['student']->id === $student->id)['totals']['total'])->toBe(0);

    $component->call('addDayGrade', $student->id, $azi, '9', 'curenta');

    $grade = Grade::query()->where('student_id', $student->id)->sole();

    expect((int) $grade->value)->toBe(9)
        ->and((int) $grade->teacher_id)->toBe($this->subjectTeacher->id)
        ->and($grade->graded_on->toDateString())->toBe($azi)
        // Memoizarea s-a invalidat: aceeași instanță arată acum nota.
        ->and(collect($component->instance()->gradeMap()['rows'])
            ->firstWhere(fn (array $r): bool => $r['student']->id === $student->id)['totals']['total'])->toBe(1);
});

it('și în panoul doar-note ora cu ABSENȚĂ e ocupată: slotul o arată, iar nota pe ea e refuzată', function () {
    // Excluderea notă↔absență pe oră e a catalogului ÎNTREG: harta Note nu afișează absențele,
    // dar sloturile ei le văd — altfel „secțiunea de note" ar fi portița prin care nota și
    // absența ajung totuși pe aceeași oră.
    actingAs($this->teacherUser);

    $student = $this->students->first();
    $azi = Carbon::today()->toDateString();

    Absence::query()->create([
        'student_id' => $student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'occurred_on' => $azi,
        'lesson_number' => 2,
        'is_motivated' => null,
    ]);

    $component = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class);

    $panel = $component->instance()->gradeDayPanel($student->id, $azi);

    expect($panel['hour_slots'][1])->toBe(['hour' => 2, 'timetable' => false, 'busy' => 'absence'])
        ->and($panel['default_hour'])->toBe(1);

    // Nota forțată pe ora absenței — refuzată de gardă; pe ora liberă trece.
    $component->call('addDayGrade', $student->id, $azi, '9', 'curenta', 2);

    expect(Grade::query()->where('student_id', $student->id)->count())->toBe(0);

    $component->call('addDayGrade', $student->id, $azi, '9', 'curenta', 1);

    expect(Grade::query()->where('student_id', $student->id)->sole()->lesson_number)->toBe(1);
});

it('panoul zilei din Note e DOAR al notelor și validează elevul pe clasa contextului', function () {
    actingAs($this->teacherUser);

    $student = $this->students->first();
    gradeMapRecord($this, $student, Carbon::today()->subDays(3)->toDateString(), 7);

    $page = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)->instance();

    $panel = $page->gradeDayPanel($student->id, Carbon::today()->subDays(3)->toDateString());

    // Fără chei de absențe: partial-ul comun își stinge singur secțiunea de absențe.
    expect($panel['grades'])->toHaveCount(1)
        ->and(array_key_exists('absences', $panel))->toBeFalse()
        ->and($panel['can_grade'])->toBeTrue();

    // Elev străin de clasă: panou gol, nimic scurs; nici scrierea nu trece.
    $foreignClass = SchoolClass::factory()->for($this->year)->create(['name' => 'IX', 'section' => 'Z', 'grade_level' => 9]);
    $foreign = Student::factory()->create();
    Enrollment::factory()->for($foreign)->for($foreignClass)->for($this->year)->create(['left_on' => null]);

    expect($page->gradeDayPanel($foreign->id, Carbon::today()->toDateString())['student'])->toBeNull();

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('addDayGrade', $foreign->id, Carbon::today()->toDateString(), '10', 'curenta');

    expect(Grade::query()->where('student_id', $foreign->id)->count())->toBe(0);
});

it('în context Diriginte harta nu notează — notarea rămâne act de profesor (F3)', function () {
    actingAs($this->homeroomUser);

    $student = $this->students->first();

    // Dirigintele (fără alocare pe disciplină) nu poate scrie note nici din hartă.
    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->call('addDayGrade', $student->id, Carbon::today()->toDateString(), '9', 'curenta');

    expect(Grade::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(Livewire::withQueryParams(['clasa' => (string) $this->class->id])
            ->test(ListGrades::class)->instance()
            ->gradeDayPanel($student->id, Carbon::today()->toDateString())['can_grade'])->toBeFalse();
});

it('zilele de LECȚIE fără note au coloană pe hartă — ușa spre scriere există mereu', function () {
    actingAs($this->teacherUser);

    // Orar: disciplina se predă lunea; nicio notă nicăieri.
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

    $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'saptamana'])
        ->test(ListGrades::class)->instance()->gradeMap();

    $day = collect($map['days'])->firstWhere('iso', $luni->toDateString());

    expect($day)->not->toBeNull()
        ->and($day['count'])->toBe(0)
        // Doar zilele de lecție: marțea nu se predă → fără coloană (nicio notă în test).
        ->and(collect($map['days'])->firstWhere('iso', $luni->copy()->addDay()->toDateString()))->toBeNull();
});

it('fără note, cine POATE nota vede rigla cu invitația; cine doar citește vede doar mesajul', function () {
    // Raportat 06.08.2026: „de ce pe Toate primesc doar mesajul, iar pe Zi/Săptămână mesajul CU un
    // tabel gol?" — rigla goală are rost doar ca suprafață de scriere. Pentru un cititor e zgomot.
    Lesson::query()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'title' => 'x',
        'teacher_name' => 'x',
        'day_of_week' => 1,
        'lesson_number' => 1,
    ]);

    // TITULARUL: zile de lecție + invitație, rigla rămâne (poate scrie prima notă).
    actingAs($this->teacherUser);

    $html = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'saptamana'])
        ->test(ListGrades::class)->assertOk()->html();

    expect($html)->toContain(trans('grade_map.empty_period_writable', ['type' => trans('enums.evaluation_type.curenta')]))
        ->and($html)->toContain('gradeDayPanel');

    // DIRIGINTELE (nu predă disciplina, deci nu notează — F3): DOAR mesajul, ca pe „Toate".
    actingAs($this->homeroomUser);

    $html = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'saptamana'])
        ->test(ListGrades::class)->assertOk()->html();

    expect($html)->toContain(trans('grade_map.empty_period', ['type' => trans('enums.evaluation_type.curenta')]))
        ->and($html)->not->toContain('gradeDayPanel');
});

it('pe „Toate" fără note mesajul e SINGUR, oricine ar privi — nu există perioadă de scris în ea', function () {
    foreach ([$this->teacherUser, $this->homeroomUser] as $viewer) {
        actingAs($viewer);

        $html = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
            ->test(ListGrades::class)->assertOk()->html();

        expect($html)->toContain(trans('grade_map.empty_period', ['type' => trans('enums.evaluation_type.curenta')]))
            ->and($html)->not->toContain('gradeDayPanel');
    }
});

it('clasă fără elevi înmatriculați: mesaj propriu, nu un tabel cu antet și zero rânduri', function () {
    // Cazul real din raport: clasa a XII-a după absolvire — toate înmatriculările au `left_on`.
    actingAs($this->director);

    Enrollment::query()
        ->where('school_class_id', $this->class->id)
        ->update(['left_on' => Carbon::today()->subMonth()->toDateString()]);

    $component = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'saptamana'])
        ->test(ListGrades::class);

    expect($component->instance()->gradeMap()['rows'])->toBe([]);

    $component->assertOk()->assertSee(trans('grade_map.no_students'));
});
