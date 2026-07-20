<?php

/**
 * Fluxul de corecție a TEMELOR (decizia beneficiarului, 2026-07-15): profesorul-autor NU își mai
 * editează tema direct — cere corecția; Directorul / Prim-vicedirectorul / Administratorul
 * Operațional aprobă (spre deosebire de notele, unde AO doar vede arhiva). Totul rămâne în arhivă.
 */

use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Filament\Resources\HomeworkCorrections\Pages\ViewHomeworkCorrection;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function hwcUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function hwcAssignment(?Teacher $teacher = null): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'subject_id' => Subject::factory(),
        'teacher_id' => $teacher?->id,
        'topic' => 'Tema veche',
        'required_task' => 'Ex. 1-3 pagina 10',
        'optional_task' => null,
    ]);
}

// ─── Cine aprobă (matricea capabilității) ────────────────────────────────────────────────

it('aprobă corecții de teme: super-admin, director, prim-vicedirector și AO; restul NU', function () {
    $allowed = [UserRole::Admin, UserRole::Director, UserRole::PrimVicedirector, UserRole::AdministratorOperational];
    $denied = [UserRole::AdministratorTehnic, UserRole::Diriginte, UserRole::Profesor, UserRole::Elev, UserRole::Parinte];

    foreach ($allowed as $role) {
        expect(hwcUser($role)->canApproveHomeworkCorrections())->toBeTrue($role->value.' ar trebui să aprobe');
    }

    foreach ($denied as $role) {
        expect(hwcUser($role)->canApproveHomeworkCorrections())->toBeFalse($role->value.' NU ar trebui să aprobe');
    }
});

it('editarea DIRECTĂ a temei e refuzată autorului-profesor și permisă aprobatorilor (policy)', function () {
    $teacherUser = hwcUser(UserRole::Profesor);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $homework = hwcAssignment($teacher);

    // Autorul NU mai poate edita direct — corectitudinea trece prin aprobare.
    expect($teacherUser->can('update', $homework))->toBeFalse()
        // Aprobatorii pot edita direct (calea excepțională).
        ->and(hwcUser(UserRole::AdministratorOperational)->can('update', $homework))->toBeTrue()
        ->and(hwcUser(UserRole::Director)->can('update', $homework))->toBeTrue()
        // Autorul își poate în continuare RETRAGE tema (soft-delete).
        ->and($teacherUser->can('delete', $homework))->toBeTrue();
});

// ─── Ciclul de viață al corecției ────────────────────────────────────────────────────────

it('aprobarea aplică pe temă DOAR câmpurile propuse și consemnează recenzentul', function () {
    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::AdministratorOperational);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_topic' => $homework->topic,
        'old_required_task' => $homework->required_task,
        'new_required_task' => 'Ex. 1-5 pagina 12',
        'reason' => 'Am greșit exercițiile.',
    ]);

    $correction->approve($reviewer->id, 'Corect.');

    $homework->refresh();
    $correction->refresh();

    expect($homework->required_task)->toBe('Ex. 1-5 pagina 12')
        // Câmpul nepropus rămâne neatins.
        ->and($homework->topic)->toBe('Tema veche')
        ->and($correction->status)->toBe(CorrectionStatus::Approved)
        ->and($correction->reviewed_by_user_id)->toBe($reviewer->id)
        ->and($correction->reviewed_at)->not->toBeNull()
        ->and($correction->review_note)->toBe('Corect.');
});

it('respingerea NU atinge tema și cere consemnarea motivului', function () {
    $homework = hwcAssignment();
    $correction = HomeworkCorrection::factory()->create([
        'homework_assignment_id' => $homework->id,
        'new_required_task' => 'Altceva',
    ]);

    $correction->reject(hwcUser(UserRole::Director)->id, 'Tema originală e corectă.');

    expect($homework->refresh()->required_task)->toBe('Ex. 1-3 pagina 10')
        ->and($correction->refresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and($correction->review_note)->toBe('Tema originală e corectă.');
});

it('o temă nu poate avea două corecții în așteptare simultan (invariant pe server)', function () {
    $homework = hwcAssignment();

    HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    expect(fn () => HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]))
        ->toThrow(ValidationException::class);
});

it('după soluționare se poate depune o NOUĂ cerere (invariantul e doar pe pending)', function () {
    $homework = hwcAssignment();

    $first = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);
    $first->reject(hwcUser(UserRole::Director)->id, 'Nu.');

    $second = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    expect($second->exists)->toBeTrue()
        ->and(HomeworkCorrection::query()->where('homework_assignment_id', $homework->id)->count())->toBe(2);
});

it('retragerea temei (soft-delete) expiră cererea în așteptare, fără recenzent', function () {
    $homework = hwcAssignment();
    $correction = HomeworkCorrection::factory()->create(['homework_assignment_id' => $homework->id]);

    $homework->delete();

    $correction->refresh();

    expect($correction->status)->toBe(CorrectionStatus::Expired)
        ->and($correction->reviewed_by_user_id)->toBeNull()
        // Cererea rămâne citibilă împreună cu tema retrasă (withTrashed).
        ->and($correction->homeworkAssignment)->not->toBeNull();
});

it('solicitantul își poate retrage cererea în așteptare (rămâne în arhivă)', function () {
    $correction = HomeworkCorrection::factory()->create();

    $correction->withdraw();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Withdrawn)
        ->and(HomeworkCorrection::query()->count())->toBe(1);
});

// ─── Accesul la resursă + curățarea demo (2026-07-20) ────────────────────────────────────

it('matricea de acces la arhiva corecțiilor de teme, pe toate rolurile', function (string $role, bool $needsTeacher, bool $allowed) {
    $user = hwcUser(UserRole::from($role));

    if ($needsTeacher) {
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    $response = $this->actingAs($user)->get('/admin/homework-corrections');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'super-admin' => [UserRole::Admin->value, false, true],
    'director' => [UserRole::Director->value, false, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector->value, false, true],
    'administrator operațional' => [UserRole::AdministratorOperational->value, false, true],
    // Personalul pedagogic vede (arhiva PROPRIE, prin scoping); fără fișă de profesor — nu.
    'profesor cu fișă' => [UserRole::Profesor->value, true, true],
    'diriginte cu fișă' => [UserRole::Diriginte->value, true, true],
    'profesor fără fișă' => [UserRole::Profesor->value, false, false],
    // Tehnicul n-are date academice; familia n-are panou.
    'administrator tehnic' => [UserRole::AdministratorTehnic->value, false, false],
    'elev' => [UserRole::Elev->value, false, false],
    'părinte' => [UserRole::Parinte->value, false, false],
]);

it('profesorul vede în arhivă DOAR cererile lui; administrația pe toate', function () {
    $mine = hwcUser(UserRole::Profesor);
    $mineTeacher = Teacher::factory()->create(['user_id' => $mine->id]);
    $other = hwcUser(UserRole::Profesor);
    $otherTeacher = Teacher::factory()->create(['user_id' => $other->id]);

    foreach ([[$mine, $mineTeacher], [$other, $otherTeacher]] as [$user, $teacher]) {
        HomeworkCorrection::create([
            'homework_assignment_id' => hwcAssignment($teacher)->id,
            'requested_by_user_id' => $user->id,
            'old_topic' => 'Tema veche',
            'new_topic' => 'Tema corectată',
            'reason' => 'motiv',
        ]);
    }

    $this->actingAs($mine);
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(1);

    $this->actingAs(hwcUser(UserRole::Director));
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(2);
});

it('purge-ul demo șterge corecțiile [DEMO] și temele-suport, dar nu atinge datele reale', function () {
    $teacher = Teacher::factory()->create();

    // Set DEMO: temă marcată + corecție marcată.
    $demoHomework = HomeworkAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'topic' => '[DEMO] Fracții ordinare',
        'required_task' => 'Ex. 1-4',
    ]);
    HomeworkCorrection::create([
        'homework_assignment_id' => $demoHomework->id,
        'requested_by_user_id' => hwcUser(UserRole::Profesor)->id,
        'old_required_task' => 'Ex. 1-4',
        'new_required_task' => 'Ex. 1-6',
        'reason' => '[DEMO] motiv de test',
    ]);

    // Set REAL: temă legacy + corecție autentică — trebuie să supraviețuiască.
    $realHomework = hwcAssignment($teacher);
    $realCorrection = HomeworkCorrection::create([
        'homework_assignment_id' => $realHomework->id,
        'requested_by_user_id' => hwcUser(UserRole::Profesor)->id,
        'old_topic' => 'Tema veche',
        'new_topic' => 'Tema corectată',
        'reason' => 'greșeală reală de redactare',
    ]);

    $this->artisan('app:purge-demo-data')->assertSuccessful();

    expect(HomeworkAssignment::withTrashed()->whereKey($demoHomework->id)->exists())->toBeFalse()
        ->and(HomeworkCorrection::query()->where('reason', 'like', '[DEMO]%')->exists())->toBeFalse()
        ->and(HomeworkAssignment::query()->whereKey($realHomework->id)->exists())->toBeTrue()
        ->and(HomeworkCorrection::query()->whereKey($realCorrection->id)->exists())->toBeTrue();
});

// ─── Fișa cererii (analiza completă înainte de decizie) ─────────────────────────────────

it('fișa arată motivul integral, propunerea vechi → nou și cronologia', function () {
    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::Director);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_required_task' => $homework->required_task,
        'new_required_task' => 'Ex. 1-6 pagina 14',
        'reason' => 'Un motiv suficient de lung încât lista îl trunchia — fișa îl arată însă integral, fără puncte de suspensie.',
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->assertSee('Un motiv suficient de lung încât lista îl trunchia — fișa îl arată însă integral, fără puncte de suspensie.')
        ->assertSee('Ex. 1-3 pagina 10')
        ->assertSee('Ex. 1-6 pagina 14')
        ->assertSee(__('panel.homework_correction_view.timeline'))
        ->assertSee(__('panel.homework_correction_view.submitted'))
        ->assertSee(__('panel.actions.approve.label'))
        ->assertSee(__('panel.actions.reject.label'));
});

it('respingerea din fișă CERE motiv, îl consemnează în cronologie și notifică solicitantul', function () {
    Notification::fake();

    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::PrimVicedirector);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_topic' => $homework->topic,
        'new_topic' => 'Tema nouă',
        'reason' => 'Titlul e greșit.',
    ]);

    $this->actingAs($reviewer);

    // Fără motiv → refuz de validare; verdictul NU se consemnează.
    Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->callAction('reject', ['review_note' => ''])
        ->assertHasActionErrors();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Pending);

    // Cu motiv → respinsă, iar solicitantul primește verdictul cu link spre fișă.
    Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->callAction('reject', ['review_note' => 'Titlul din manual e cel vechi.'])
        ->assertNotified();

    expect($correction->refresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and($correction->review_note)->toBe('Titlul din manual e cel vechi.');

    Notification::assertSentTo(
        $requester,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::HomeworkCorrectionRejected
            && $n->url !== null && str_contains($n->url, (string) $correction->id),
    );
});

it('aprobarea din fișă aplică schimbarea; retragerea e doar a solicitantului', function () {
    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::AdministratorOperational);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_required_task' => $homework->required_task,
        'new_required_task' => 'Ex. 7-9 pagina 20',
        'reason' => 'Completare.',
    ]);

    // Aprobatorul NU vede „Retrage" (nu e cererea lui); solicitantul o vede pe a lui.
    $this->actingAs($reviewer);
    Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->assertActionHidden('withdraw')
        ->callAction('approve', ['review_note' => 'De acord.'])
        ->assertNotified();

    expect($homework->refresh()->required_task)->toBe('Ex. 7-9 pagina 20')
        ->and($correction->refresh()->status)->toBe(CorrectionStatus::Approved);

    // După verdict, judecata dispare de pe fișă.
    Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});

it('fișa e a arhivarilor sau a solicitantului propriu — alt profesor primește 404', function () {
    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    // Ambii cu fișă de profesor: poarta resursei (canSeeAcademicData) cere fișa.
    Teacher::factory()->create(['user_id' => $requester->id]);
    $stranger = hwcUser(UserRole::Profesor);
    Teacher::factory()->create(['user_id' => $stranger->id]);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_topic' => $homework->topic,
        'new_topic' => 'Alt titlu',
        'reason' => 'Motiv.',
    ]);

    // Scoping-ul din getEloquentQuery ASCUNDE cererile străine → 404 (nici măcar existența
    // nu se confirmă), mai puternic decât un 403.
    $this->actingAs($stranger)
        ->get("/admin/homework-corrections/{$correction->id}")
        ->assertNotFound();

    $this->actingAs($requester)
        ->get("/admin/homework-corrections/{$correction->id}")
        ->assertOk();
});

it('judecata lasă urmă în jurnalul de audit (modelul e auditabil)', function () {
    // Auditarea e oprită implicit în consolă (config audit.console=false) — pornită pentru test.
    config(['audit.console' => true]);

    $homework = hwcAssignment();
    $requester = hwcUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::Director);

    $correction = HomeworkCorrection::create([
        'homework_assignment_id' => $homework->id,
        'requested_by_user_id' => $requester->id,
        'old_topic' => $homework->topic,
        'new_topic' => 'Titlu auditat',
        'reason' => 'Motiv.',
    ]);

    $this->actingAs($reviewer);
    $correction->approve($reviewer->id, 'Ok.');

    expect(DB::table('audits')
        ->where('auditable_type', HomeworkCorrection::class)
        ->where('auditable_id', $correction->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();
});
