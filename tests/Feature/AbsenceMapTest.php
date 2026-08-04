<?php

/**
 * HARTA ABSENȚELOR (cerința beneficiarului, 04.08.2026): în context de clasă, secțiunea Absențe
 * arată elevi × zile în loc de „un rând per absență" — statutul se fixează direct din pastilă.
 *
 * Testele fixează cele trei promisiuni:
 *  - HARTA e fidelă: aceiași ochelari ca tabelul (interogarea scoped + perioada), toți elevii
 *    clasei pe rânduri, totalurile corecte;
 *  - LUCRUL DIRECT ține gărzile: statutul îl fixează dirigintele clasei sau administrația, pe
 *    server — profesorul de disciplină și id-urile străine de context sunt refuzate;
 *  - FORMA nu ascunde nimic: lista clasică rămâne la un click, iar perioadele prea largi cer
 *    restrângere în loc să taie tăcut coloane.
 */

use App\Enums\UserRole;
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
        'starts_on' => Carbon::today()->subMonths(3)->toDateString(),
        'ends_on' => Carbon::today()->addMonths(2)->toDateString(),
        'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'VIII', 'section' => 'A', 'grade_level' => 8]);
    $this->subject = Subject::factory()->create(['name' => 'Chimie', 'abbreviation' => 'Ch']);

    // Dirigintele clasei — el statutează.
    $homeroomUser = User::factory()->create();
    $homeroomUser->assignRole(UserRole::Diriginte->value);
    $this->homeroomTeacher = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $this->class->update(['homeroom_teacher_id' => $this->homeroomTeacher->id]);
    $this->homeroomUser = $homeroomUser->fresh();

    // Profesor de disciplină la aceeași clasă — el doar consemnează.
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $this->subjectTeacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $this->teacherUser = $teacherUser->fresh();

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->subjectTeacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // Trei elevi; al treilea NU va avea absențe — rândul lui gol e informația.
    $this->students = collect(['Vulpe', 'Barbu', 'Neagu'])->map(function (string $name) {
        $student = Student::factory()->create(['last_name' => $name, 'first_name' => 'Elev']);
        Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create([
            'enrolled_on' => Carbon::today()->subMonths(3),
            'left_on' => null,
        ]);

        return $student;
    });
});

/** Absență în clasa de test, cu statutul și ziua cerute. */
function absenceMapRecord(object $ctx, Student $student, string $date, ?bool $motivated = null, bool $wholeDay = false): Absence
{
    return Absence::query()->create([
        'student_id' => $student->id,
        'subject_id' => $wholeDay ? null : $ctx->subject->id,
        'school_class_id' => $ctx->class->id,
        'term_id' => $ctx->term->id,
        'teacher_id' => $ctx->subjectTeacher->id,
        'occurred_on' => $date,
        'is_motivated' => $motivated,
    ]);
}

it('harta e vederea implicită în context de clasă, iar lista rămâne la un click', function () {
    actingAs($this->homeroomUser);

    $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListAbsences::class)
        ->instance();

    expect($map->showsAbsenceMap())->toBeTrue();

    // ?forma=lista → tabelul clasic; fără context de clasă → tot tabelul (celelalte dimensiuni).
    $list = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'forma' => 'lista'])
        ->test(ListAbsences::class);

    expect($list->instance()->showsAbsenceMap())->toBeFalse()
        ->and(Livewire::test(ListAbsences::class)->instance()->showsAbsenceMap())->toBeFalse();

    // Drumul înapoi există în AMBELE sensuri: harta oferă „Vezi lista", lista oferă „Vezi harta".
    $list->assertSee('setAbsenceView', escape: false);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListAbsences::class)
        ->assertSee('setAbsenceView', escape: false);
});

it('rândurile cuprind TOATĂ clasa alfabetic, coloanele doar zilele cu absențe, totalurile pe statut', function () {
    [$vulpe, $barbu] = $this->students->all();

    $zi1 = Carbon::today()->subDays(4)->toDateString();
    $zi2 = Carbon::today()->subDays(1)->toDateString();

    absenceMapRecord($this, $vulpe, $zi1);
    absenceMapRecord($this, $vulpe, $zi2, motivated: true);
    absenceMapRecord($this, $vulpe, $zi2, motivated: false, wholeDay: true);
    absenceMapRecord($this, $barbu, $zi2);

    actingAs($this->homeroomUser);

    // „Toate" explicit: zilele relative ale testului pot cădea în luni diferite, iar aici se
    // verifică STRUCTURA hărții, nu perioada implicită (aceea are testul ei).
    $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListAbsences::class)
        ->instance()
        ->absenceMap();

    // Coloane: DOAR zilele cu absențe, cronologic — nu tot calendarul lunii.
    expect(array_column($map['days'], 'iso'))->toBe([$zi1, $zi2])
        ->and($map['canStatus'])->toBeTrue();

    // Rânduri: toată clasa, alfabetic — inclusiv eleva fără nicio absență.
    $names = array_map(fn (array $row): string => (string) $row['student']->last_name, $map['rows']);

    expect($names)->toBe(['Barbu', 'Neagu', 'Vulpe']);

    $rows = collect($map['rows'])->keyBy(fn (array $row): int => (int) $row['student']->id);

    expect($rows[$vulpe->id]['totals'])->toBe(['total' => 3, 'motivated' => 1, 'unmotivated' => 1, 'pending' => 1])
        ->and($rows[$barbu->id]['totals']['total'])->toBe(1)
        // Celula zilei 2 a lui Vulpe ține AMBELE absențe (disciplină + zi întreagă).
        ->and($rows[$vulpe->id]['cells'][$zi2])->toHaveCount(2);

    // Eticheta vizibilă e MEREU marcajul „A" (cerința 04.08.2026) — deosebirea dintre absențe
    // trăiește în title (hover + nume accesibil): disciplina, respectiv „zi întreagă", plus statutul.
    $cellChips = collect($rows[$vulpe->id]['cells'][$zi2]);

    expect($cellChips->pluck('label')->unique()->all())->toBe([__('absence_map.marker')])
        ->and($cellChips->pluck('title')->join(' '))->toContain(__('absence_map.whole_day'))
        ->and(collect($rows[$vulpe->id]['cells'][$zi1])->pluck('title')->join(' '))->toContain('Chimie');
});

it('dirigintele fixează statutul direct din hartă — inclusiv înapoi la „fără statut"', function () {
    $absence = absenceMapRecord($this, $this->students->first(), Carbon::today()->subDays(2)->toDateString());

    actingAs($this->homeroomUser);

    $page = Livewire::withQueryParams(['clasa' => (string) $this->class->id])->test(ListAbsences::class);

    $page->call('setAbsenceMapStatus', $absence->id, 'motivated');
    expect($absence->fresh()->is_motivated)->toBeTrue();

    $page->call('setAbsenceMapStatus', $absence->id, 'unmotivated');
    expect($absence->fresh()->is_motivated)->toBeFalse();

    // Revenirea la „fără statut" = corectarea unui click greșit; rămâne în jurnalul de audit.
    $page->call('setAbsenceMapStatus', $absence->id, 'pending');
    expect($absence->fresh()->is_motivated)->toBeNull();
});

it('profesorul de disciplină vede harta, dar NU poate statuta — nici cu apel direct', function () {
    $absence = absenceMapRecord($this, $this->students->first(), Carbon::today()->subDays(2)->toDateString());

    actingAs($this->teacherUser);

    $page = Livewire::withQueryParams(['clasa' => (string) $this->class->id])->test(ListAbsences::class);

    expect($page->instance()->absenceMap()['canStatus'])->toBeFalse();

    // Blade-ul nu-i arată butoanele, dar garda reală e pe server: apelul direct e refuzat.
    $page->call('setAbsenceMapStatus', $absence->id, 'motivated');

    expect($absence->fresh()->is_motivated)->toBeNull();
});

it('o absență din afara contextului nu poate fi statutată prin hartă', function () {
    // Aceeași școală, altă clasă — dirigintele nostru n-are nicio calitate acolo.
    $otherClass = SchoolClass::factory()->for($this->year)->create(['name' => 'V', 'section' => 'B', 'grade_level' => 5]);
    $stranger = Student::factory()->create();
    Enrollment::factory()->for($stranger)->for($otherClass)->for($this->year)->create(['left_on' => null]);

    $foreign = Absence::query()->create([
        'student_id' => $stranger->id,
        'subject_id' => null,
        'school_class_id' => $otherClass->id,
        'term_id' => $this->term->id,
        'teacher_id' => $this->subjectTeacher->id,
        'occurred_on' => Carbon::today()->subDays(2)->toDateString(),
        'is_motivated' => null,
    ]);

    actingAs($this->homeroomUser);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListAbsences::class)
        ->call('setAbsenceMapStatus', $foreign->id, 'motivated');

    expect($foreign->fresh()->is_motivated)->toBeNull();
});

it('perioada implicită e LUNA curentă; absențele vechi apar doar pe „Toate"', function () {
    $student = $this->students->first();

    absenceMapRecord($this, $student, Carbon::today()->toDateString());
    absenceMapRecord($this, $student, Carbon::today()->subDays(40)->toDateString());

    actingAs($this->homeroomUser);

    $default = Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListAbsences::class)
        ->instance();

    expect($default->timeMode())->toBe('luna')
        ->and(array_column($default->absenceMap()['days'], 'iso'))->toBe([Carbon::today()->toDateString()]);

    $all = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListAbsences::class)
        ->instance();

    expect($all->absenceMap()['days'])->toHaveCount(2);
});

it('oricâte zile ar avea perioada, toate rămân coloane — nimic tăiat, nimic refuzat', function () {
    /**
     * Fostul plafon (31 de zile → mesaj „restrângeți perioada") a fost scos la decizia
     * beneficiarului (04.08.2026): surplusul se parcurge orizontal cu săgețile de carusel, iar
     * numele și totalurile stau ancorate la capete. Testul apără NOUL contract: nicio zi nu
     * dispare și niciun refuz nu se întoarce pe tăcute.
     */
    $student = $this->students->first();

    for ($i = 0; $i < 40; $i++) {
        absenceMapRecord($this, $student, Carbon::today()->subDays(80 - $i)->toDateString());
    }

    actingAs($this->homeroomUser);

    $map = Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'mod' => 'toate'])
        ->test(ListAbsences::class)
        ->instance()
        ->absenceMap();

    $rows = collect($map['rows'])->keyBy(fn (array $row): int => (int) $row['student']->id);

    expect($map['days'])->toHaveCount(40)
        ->and($rows[$student->id]['totals']['total'])->toBe(40);
});
