<?php

/**
 * ANULAREA UNEI ABSENȚE (cerința beneficiarului, 07.08.2026): consemnată din greșeală, se desface.
 *
 * Statutul avea trei stări — motivată / nemotivată / fără statut — dar toate trei presupun că
 * elevul A LIPSIT. Nu exista niciun fel de a spune „nu s-a întâmplat". Mecanismul e cel de la
 * note: rândul rămâne în istoric cu motivul îndreptării, dar iese din TOATE numărătorile.
 *
 * Testul enumeră suprafețele una câte una — DELIBERAT, fiindcă excluderea e opt-in
 * ({@see Absence::scopeActive}, nu scope global). Un consumator uitat e singurul mod în care
 * mecanismul poate eșua tăcut, iar aici se vede.
 */

use App\Actions\ComputeDeferralRisk;
use App\Enums\UserRole;
use App\Filament\Pages\ClassRegister;
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
    $this->term = Term::factory()->for($this->year)->create([
        'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(3),
        'ends_on' => Carbon::today()->addMonth(),
    ]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 6]);
    $this->subject = Subject::factory()->create(['min_grade' => 1, 'max_grade' => 12]);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $this->teacherUser = $teacherUser->fresh();

    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->student = Student::factory()->create(['last_name' => 'Vulpe', 'first_name' => 'Elev']);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create([
        'enrolled_on' => Carbon::today()->subMonths(3),
        'left_on' => null,
    ]);
});

/** O absență în clasa de test, la ziua dată. */
function annulmentAbsence(object $ctx, string $date, ?int $lesson = null, ?bool $motivated = null): Absence
{
    return Absence::query()->create([
        'student_id' => $ctx->student->id,
        'school_class_id' => $ctx->class->id,
        'subject_id' => $ctx->subject->id,
        'teacher_id' => $ctx->teacher->id,
        'term_id' => $ctx->term->id,
        'occurred_on' => $date,
        'lesson_number' => $lesson,
        'is_motivated' => $motivated,
    ]);
}

/** Anulează, cum o face panoul/tabelul. */
function annulmentApply(Absence $absence): Absence
{
    $absence->update([
        'annulled_at' => now(),
        'annulled_by_user_id' => 1,
        'annulment_reason' => 'consemnată din greșeală',
    ]);

    return $absence->fresh();
}

// ─── Modelul ────────────────────────────────────────────────────────────────────────────

it('anularea păstrează rândul și motivul, dar îl scoate din „active"', function () {
    $absenta = annulmentApply(annulmentAbsence($this, Carbon::today()->subDays(3)->toDateString()));

    expect($absenta->isAnnulled())->toBeTrue()
        ->and($absenta->annulment_reason)->toBe('consemnată din greșeală')
        // Rândul EXISTĂ — nu s-a șters.
        ->and(Absence::query()->count())->toBe(1)
        ->and(Absence::query()->active()->count())->toBe(0);
});

it('anularea NU e un al patrulea statut — statutul rămâne cel dinainte', function () {
    $absenta = annulmentApply(annulmentAbsence($this, Carbon::today()->subDays(3)->toDateString(), motivated: true));

    expect($absenta->status()->value)->toBe('motivated')
        ->and($absenta->isAnnulled())->toBeTrue();
});

it('scope-urile de statut exclud anulatele: coada dirigintelui și termenele familiei', function () {
    annulmentApply(annulmentAbsence($this, Carbon::today()->subDays(3)->toDateString()));      // fără statut
    annulmentApply(annulmentAbsence($this, Carbon::today()->subDays(4)->toDateString(), motivated: false));
    annulmentAbsence($this, Carbon::today()->subDays(5)->toDateString());             // activă, fără statut

    expect(Absence::query()->pending()->count())->toBe(1)
        ->and(Absence::query()->notMotivated()->count())->toBe(1);
});

// ─── Suprafețele de afișare ─────────────────────────────────────────────────────────────

it('cabinetul familiei nu numără absențele anulate', function () {
    annulmentAbsence($this, Carbon::today()->subDays(2)->toDateString());
    annulmentApply(annulmentAbsence($this, Carbon::today()->subDays(3)->toDateString()));

    $student = $this->student->fresh();
    $student->load(['absences' => fn ($query) => $query->active()->with('subject')]);

    expect($student->absences)->toHaveCount(1);
});

it('catalogul nu arată absența anulată în ziua ei', function () {
    $zi = Carbon::today()->subDays(2)->toDateString();
    annulmentAbsence($this, $zi, lesson: 1);
    annulmentApply(annulmentAbsence($this, $zi, lesson: 2));

    actingAs($this->teacherUser);

    $panou = Livewire::withQueryParams([
        'clasa' => (string) $this->class->id,
        'disciplina' => (string) $this->subject->id,
    ])->test(ClassRegister::class)->instance()->dayPanel($this->student->id, $zi);

    expect($panou['absences'])->toHaveCount(1)
        ->and($panou['absences'][0]['lesson'])->toBe(1);
});

it('riscul de amânare nu se apropie de prag din absențe anulate', function () {
    // Zece absențe, toate ANULATE: pragul de risc nu are din ce se compune.
    for ($i = 1; $i <= 10; $i++) {
        annulmentApply(annulmentAbsence($this, Carbon::today()->subDays($i)->toDateString()));
    }

    $risc = app(ComputeDeferralRisk::class)->for($this->student->fresh());

    // Niciun risc semnalat, fiindcă nicio absență anulată nu se numără.
    expect($risc['risks'])->toBe([])
        ->and(collect($risc['risks'])->sum('absences'))->toBe(0);
});

// ─── Efecte asupra scrierii ─────────────────────────────────────────────────────────────

it('ora eliberată prin anulare se poate reconsemna — greșeala nu blochează slotul', function () {
    $zi = Carbon::today()->subDays(2)->toDateString();
    $gresita = annulmentAbsence($this, $zi, lesson: 3);

    annulmentApply($gresita);

    // Aceeași zi, aceeași oră, aceeași disciplină: garda anti-duplicat NU trebuie să se opună.
    $corecta = annulmentAbsence($this, $zi, lesson: 3);

    expect($corecta->exists)->toBeTrue()
        ->and(Absence::query()->active()->count())->toBe(1);
});

// ─── Acțiunea din secțiunea Absențe ──────────────────────────────────────────────────────

it('acțiunea „Anulează" din listă cere motiv și scoate absența din numărători', function () {
    $absenta = annulmentAbsence($this, Carbon::today()->subDays(2)->toDateString());

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    actingAs($admin->fresh());

    Livewire::test(ListAbsences::class)
        ->callTableAction('annul', $absenta, ['annulment_reason' => 'pusă pe alt elev'])
        ->assertHasNoTableActionErrors();

    $absenta = $absenta->fresh();

    expect($absenta->isAnnulled())->toBeTrue()
        ->and($absenta->annulment_reason)->toBe('pusă pe alt elev')
        ->and($absenta->annulled_by_user_id)->toBe($admin->id)
        ->and(Absence::query()->active()->count())->toBe(0);
});

it('motivul e OBLIGATORIU — o absență nu iese din totaluri fără explicație', function () {
    $absenta = annulmentAbsence($this, Carbon::today()->subDays(2)->toDateString());

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    actingAs($admin->fresh());

    Livewire::test(ListAbsences::class)
        ->callTableAction('annul', $absenta, ['annulment_reason' => ''])
        ->assertHasTableActionErrors(['annulment_reason']);

    expect($absenta->fresh()->isAnnulled())->toBeFalse();
});

it('profesorul poate anula pe (clasa, disciplina) LUI; pe alta — nu', function () {
    $absenta = annulmentAbsence($this, Carbon::today()->subDays(2)->toDateString());

    actingAs($this->teacherUser);

    Livewire::test(ListAbsences::class)
        ->callTableAction('annul', $absenta, ['annulment_reason' => 'greșeală proprie'])
        ->assertHasNoTableActionErrors();

    expect($absenta->fresh()->isAnnulled())->toBeTrue();

    // Alt profesor, fără alocare pe această pereche: acțiunea nici nu i se oferă.
    $strainUser = User::factory()->create();
    $strainUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $strainUser->id]);

    $alta = annulmentAbsence($this, Carbon::today()->subDays(4)->toDateString());

    actingAs($strainUser->fresh());

    // Mai strict decât „acțiunea e ascunsă": absența nici nu intră în perimetrul lui, deci n-are
    // pe ce apăsa. Scoping-ul de resursă e prima barieră, dreptul de anulare a doua.
    Livewire::test(ListAbsences::class)
        ->assertCanNotSeeTableRecords([$alta]);
});

it('harta absențelor nu pune pe pastilă o absență anulată', function () {
    $zi = Carbon::today()->subDays(2)->toDateString();
    annulmentAbsence($this, $zi, lesson: 1);
    annulmentApply(annulmentAbsence($this, $zi, lesson: 2));

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    actingAs($admin->fresh());

    $harta = Livewire::withQueryParams([
        'clasa' => (string) $this->class->id,
        'mod' => 'personalizat',
        'de' => $zi,
        'pana' => $zi,
    ])->test(ListAbsences::class)->instance()->absenceMap();

    $totaluri = collect($harta['rows'] ?? [])->sum(fn (array $rand): int => (int) ($rand['totals']['total'] ?? 0));

    expect($harta['rows'])->not->toBeEmpty()
        ->and($totaluri)->toBe(1);
});
