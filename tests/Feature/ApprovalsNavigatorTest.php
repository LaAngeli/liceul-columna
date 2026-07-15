<?php

/**
 * Cozile de aprobare ca navigator (2026-07-16): vederi pe STAREA cererii — „De procesat"
 * (implicit) / „Arhivă" — apoi carduri pe entitate (Corecții note/teme = solicitantul;
 * Motivări = clasa curentă a elevului) → tabelul în context, cu acțiunile existente.
 * Solicitantul fără drept de procesare păstrează tabelul plat cu cererile proprii
 * (GradeCorrectionLifecycleTest rămâne sursa acelui flux).
 */

use App\Enums\CorrectionStatus;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\Pages\ListAbsenceMotivations;
use App\Filament\Resources\GradeCorrections\Pages\ListGradeCorrections;
use App\Filament\Resources\HomeworkCorrections\Pages\ListHomeworkCorrections;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeCorrection;
use App\Models\HomeworkCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

// ─── Corecții de note: carduri pe solicitant ─────────────────────────────────────────────

it('coada corecțiilor de note: vederi cu numărătoare, carduri pe solicitant, context', function () {
    $anna = User::factory()->create(['name' => 'AP-Anna']);
    $bogdan = User::factory()->create(['name' => 'AP-Bogdan']);

    $annaPending1 = GradeCorrection::factory()->create(['requested_by_user_id' => $anna->id]);
    $annaPending2 = GradeCorrection::factory()->create(['requested_by_user_id' => $anna->id]);
    $bogdanPending = GradeCorrection::factory()->create(['requested_by_user_id' => $bogdan->id]);
    $annaApproved = GradeCorrection::factory()->create([
        'requested_by_user_id' => $anna->id, 'status' => CorrectionStatus::Approved,
    ]);

    actingAs($this->director);

    $component = Livewire::test(ListGradeCorrections::class);
    $page = $component->instance();

    expect($page->isQueueManagerView())->toBeTrue()
        ->and(collect($page->approvalViewPills())->pluck('count')->all())->toBe([3, 1]);

    // Cardurile cozii: pe solicitant, ordonate alfabetic, doar cu cereri în așteptare.
    $cards = $page->approvalCards();
    expect(collect($cards)->pluck('title')->all())->toBe(['AP-Anna', 'AP-Bogdan'])
        ->and($cards[0]['stats'][0])->toContain('2');

    // Contextul solicitantului: doar cererile LUI în așteptare.
    $component->call('openTarget', $anna->id)
        ->assertCanSeeTableRecords([$annaPending1, $annaPending2])
        ->assertCanNotSeeTableRecords([$bogdanPending, $annaApproved]);

    // Arhiva: doar cererile judecate.
    $component->call('leaveTarget')->call('setApprovalView', 'arhiva');
    expect(collect($component->instance()->approvalCards())->pluck('title')->all())->toBe(['AP-Anna']);

    $component->call('openTarget', $anna->id)
        ->assertCanSeeTableRecords([$annaApproved])
        ->assertCanNotSeeTableRecords([$annaPending1]);
});

it('un solicitant străin venit prin URL nu deschide context, iar coada rămâne completă', function () {
    $pending = GradeCorrection::factory()->create();

    actingAs($this->director);

    $component = Livewire::withQueryParams(['solicitant' => '999999'])->test(ListGradeCorrections::class);

    expect($component->instance()->activeTargetId())->toBeNull();
});

it('profesorul-solicitant păstrează tabelul plat cu TOATE cererile lui (orice stare)', function () {
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $profesor->id]);

    $pending = GradeCorrection::factory()->create(['requested_by_user_id' => $profesor->id]);
    $rejected = GradeCorrection::factory()->create([
        'requested_by_user_id' => $profesor->id, 'status' => CorrectionStatus::Rejected,
    ]);

    actingAs($profesor);

    $component = Livewire::test(ListGradeCorrections::class);

    expect($component->instance()->isQueueManagerView())->toBeFalse();

    // Fără constrângerea de vedere: istoricul propriu complet, ca înainte.
    $component->assertCanSeeTableRecords([$pending, $rejected]);
});

// ─── Corecții de teme: aceeași rețetă ────────────────────────────────────────────────────

it('coada corecțiilor de teme funcționează pe aceeași rețetă (solicitant → cereri)', function () {
    $autor = User::factory()->create(['name' => 'AP-Autor']);
    $pending = HomeworkCorrection::factory()->create(['requested_by_user_id' => $autor->id]);
    $withdrawn = HomeworkCorrection::factory()->create([
        'requested_by_user_id' => $autor->id, 'status' => CorrectionStatus::Withdrawn,
    ]);

    actingAs($this->director);

    $component = Livewire::test(ListHomeworkCorrections::class);
    $page = $component->instance();

    expect(collect($page->approvalViewPills())->pluck('count')->all())->toBe([1, 1])
        ->and(collect($page->approvalCards())->pluck('id')->all())->toBe([$autor->id]);

    $component->call('openTarget', $autor->id)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$withdrawn]);
});

// ─── Motivări: carduri pe clasa curentă a elevului ───────────────────────────────────────

it('motivările se grupează pe clasa curentă; dirigintele cu o clasă aterizează direct în ea', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $classB = SchoolClass::factory()->for($year)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'B']);

    $anaA = Student::factory()->create();
    Enrollment::factory()->for($anaA)->for($classA)->for($year)->create();
    $ionB = Student::factory()->create();
    Enrollment::factory()->for($ionB)->for($classB)->for($year)->create();

    $pendingA = AbsenceMotivation::factory()->create(['student_id' => $anaA->id]);
    $pendingB = AbsenceMotivation::factory()->create(['student_id' => $ionB->id]);
    $approvedA = AbsenceMotivation::factory()->create(['student_id' => $anaA->id, 'status' => RequestStatus::Approved]);

    // Administrația: carduri pentru ambele clase, în ordinea catalogului.
    actingAs($this->director);

    $component = Livewire::test(ListAbsenceMotivations::class);
    $page = $component->instance();

    expect(collect($page->approvalCards())->pluck('id')->all())->toBe([$classA->id, $classB->id])
        ->and($page->activeTargetId())->toBeNull();

    $component->call('openTarget', $classA->id)
        ->assertCanSeeTableRecords([$pendingA])
        ->assertCanNotSeeTableRecords([$pendingB, $approvedA]);

    // Dirigintele clasei A: un singur card → contextul se deschide SINGUR (fallback, fără „înapoi").
    $dirig = User::factory()->create();
    $dirig->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $dirig->id]);
    $classA->update(['homeroom_teacher_id' => $teacher->id]);

    actingAs($dirig);

    $dirigComponent = Livewire::test(ListAbsenceMotivations::class);
    $dirigPage = $dirigComponent->instance();

    expect($dirigPage->activeTargetId())->toBe($classA->id)
        ->and($dirigPage->isFallbackTarget())->toBeTrue();

    $dirigComponent
        ->assertCanSeeTableRecords([$pendingA])
        ->assertCanNotSeeTableRecords([$pendingB, $approvedA]);

    // Arhiva dirigintelui: cererea judecată a clasei lui.
    $dirigComponent->call('setApprovalView', 'arhiva')
        ->assertCanSeeTableRecords([$approvedA])
        ->assertCanNotSeeTableRecords([$pendingA]);
});

it('cererea peste termen ridică badge-ul de urgență pe cardul clasei', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['name' => 'VIII', 'grade_level' => 8, 'section' => 'C']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $overdue = AbsenceMotivation::factory()->create(['student_id' => $student->id]);
    $overdue->forceFill(['created_at' => now()->subDays(10)])->save();

    actingAs($this->director);

    $cards = Livewire::test(ListAbsenceMotivations::class)->instance()->approvalCards();

    expect($cards[0]['badge'])->not->toBeNull()
        ->and($cards[0]['badge'])->toContain('1');
});
