<?php

/**
 * HARTA NOTELOR (cerința beneficiarului, 05.08.2026 — sora hărții absențelor, pe unitatea NOTĂ).
 *
 * Testele fixează promisiunile proprii notelor:
 *  - VALOAREA e eticheta (întreagă, fără zecimalele castului), pragul dă culoarea, sumativa
 *    accentul; notele ANULATE nu există pe hartă (nu contează la medii);
 *  - totalurile sunt NUMĂRĂTORI (note / sub 5 / sumative), deliberat fără medii;
 *  - pastila poartă DOAR pârghiile privitorului — nimic care să ducă în 403 (lecția absențelor);
 *  - acțiunile din hartă trec prin ACELEAȘI gărzi ca tabelul, pe server, iar efectele curg mai
 *    departe: anularea recalculează mediile, corecția intră în coada de aprobare.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
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
    gradeMapRecord($this, $barbu, $zi, 10, 'curenta', $this->otherSubject);
    gradeMapRecord($this, $barbu, $zi, 2, annulled: true); // ANULATĂ — nu există pe hartă

    actingAs($this->director);

    $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->instance()
        ->gradeMap();

    $rows = collect($map['rows'])->keyBy(fn (array $row): int => (int) $row['student']->id);
    $vulpeCell = $rows[$vulpe->id]['cells'][$zi];

    // Toată clasa pe rânduri, alfabetic — inclusiv elevul fără note.
    expect(array_map(fn (array $r): string => (string) $r['student']->last_name, $map['rows']))
        ->toBe(['Barbu', 'Neagu', 'Vulpe'])
        // Trei pastile la Vulpe: duplicatele aceleiași discipline rămân separate.
        ->and($vulpeCell)->toHaveCount(3)
        // Eticheta e ÎNTREAGĂ — nu zecimalele castului (9.00).
        ->and(array_column($vulpeCell, 'label'))->toBe(['9', '4', '8'])
        ->and(array_column($vulpeCell, 'below'))->toBe([false, true, false])
        ->and(array_column($vulpeCell, 'summative'))->toBe([false, false, true])
        // Eticheta tipului urmează CICLUL (gimnaziu → ESS).
        ->and(collect($vulpeCell)->firstWhere('summative', true)['type_label'])->toContain('ESS')
        ->and($rows[$vulpe->id]['totals'])->toBe(['total' => 3, 'below' => 1, 'summative' => 1])
        // Anulata nu apare NICĂIERI: nici pastilă, nici în totaluri.
        ->and($rows[$barbu->id]['totals'])->toBe(['total' => 1, 'below' => 0, 'summative' => 0])
        ->and(collect($rows[$barbu->id]['cells'][$zi])->pluck('label')->all())->toBe(['10']);
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

    // DIRIGINTELE (nu predă nimic): vede ambele note, poate DOAR solicita corecție.
    $homeroomChips = collect($chipsFor($this->homeroomUser))->keyBy('id');

    expect($homeroomChips)->toHaveCount(2)
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
        ->callAction('annulGrade', ['annulment_reason' => 'greșeală de introducere'], arguments: ['id' => $slaba->id]);

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
        ->callAction('requestGradeCorrection', ['new_value' => 6, 'reason' => 'media reală e alta'], arguments: ['id' => $grade->id]);

    expect(GradeCorrection::query()->where('grade_id', $grade->id)->count())->toBe(1)
        ->and($grade->fresh()->hasPendingCorrection())->toBeTrue()
        // Nota NU s-a schimbat — cererea așteaptă aprobarea administrației.
        ->and((int) $grade->fresh()->value)->toBe(5);

    // A doua cerere, cât prima așteaptă: refuzată pe server (gardă + invariant în observer).
    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->callAction('requestGradeCorrection', ['new_value' => 7, 'reason' => 'a doua'], arguments: ['id' => $grade->id]);

    expect(GradeCorrection::query()->where('grade_id', $grade->id)->count())->toBe(1);
});

it('un profesor STRĂIN de pereche nu poate anula nici cu apel forțat', function () {
    $grade = gradeMapRecord($this, $this->students->first(), Carbon::today()->subDays(2)->toDateString(), 9, 'curenta', $this->otherSubject);

    // Titularul de CHIMIE forțează anularea notei de ISTORIE (nu e perechea lui): interogarea
    // scoped nici nu-i întoarce nota → refuz, nota neatinsă.
    actingAs($this->teacherUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListGrades::class)
        ->callAction('annulGrade', ['annulment_reason' => 'încercare'], arguments: ['id' => $grade->id]);

    expect($grade->fresh()->isAnnulled())->toBeFalse();
});
