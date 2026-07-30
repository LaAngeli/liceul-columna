<?php

/**
 * Corecția DIRECTĂ a temelor (decizia beneficiarului, 2026-07-31 — o răstoarnă pe cea din
 * 2026-07-15): profesorul-autor și dirigintele clasei editează conținutul FĂRĂ aprobare, iar
 * registrul {@see HomeworkCorrection} consemnează automat vechi → nou (observer). Secțiunea
 * „Corecții teme" e REGISTRU în grupul Catalog, EXCLUSIV al personalului pedagogic —
 * administrația nu o mai are (supravegherea rămâne prin Jurnalul de audit).
 */

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Filament\Resources\HomeworkCorrections\Pages\ViewHomeworkCorrection;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ActiveRole;
use Illuminate\Support\Facades\DB;
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

/** @return array{0: User, 1: Teacher} */
function hwcTeacherUser(UserRole $role): array
{
    $user = hwcUser($role);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    return [$user, $teacher];
}

function hwcAssignment(?Teacher $teacher = null, array $attributes = []): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'subject_id' => Subject::factory(),
        'teacher_id' => $teacher?->id,
        'topic' => 'Tema veche',
        'required_task' => 'Ex. 1-3 pagina 10',
        'optional_task' => null,
        ...$attributes,
    ]);
}

// ─── Drepturile de editare (policy — cine corectează direct) ────────────────────────────

it('autorul își corectează tema direct; alt profesor NU; administrația păstrează editarea', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    [$strangerUser] = hwcTeacherUser(UserRole::Profesor);
    $homework = hwcAssignment($author);

    expect($authorUser->can('update', $homework))->toBeTrue()
        ->and($strangerUser->can('update', $homework))->toBeFalse()
        ->and(hwcUser(UserRole::Director)->can('update', $homework))->toBeTrue()
        ->and(hwcUser(UserRole::AdministratorOperational)->can('update', $homework))->toBeTrue()
        // Autorul își poate în continuare RETRAGE tema (soft-delete).
        ->and($authorUser->can('delete', $homework))->toBeTrue();
});

it('dirigintele corectează temele CLASEI lui — nu ale altei clase, nu pe toată treapta', function () {
    [$homeroomUser, $homeroom] = hwcTeacherUser(UserRole::Diriginte);
    SchoolClass::factory()->create(['grade_level' => 7, 'section' => 'A', 'homeroom_teacher_id' => $homeroom->id]);
    [, $author] = hwcTeacherUser(UserRole::Profesor);

    $ownClass = hwcAssignment($author, ['grade_level' => 7, 'section' => 'A']);
    $otherClass = hwcAssignment($author, ['grade_level' => 7, 'section' => 'B']);
    // Tema pe TOATĂ treapta ar afecta și clasele altor diriginți → rămâne pe autor/administrație.
    $wholeGrade = hwcAssignment($author, ['grade_level' => 7, 'section' => null]);

    expect($homeroomUser->can('update', $ownClass))->toBeTrue()
        ->and($homeroomUser->can('update', $otherClass))->toBeFalse()
        ->and($homeroomUser->can('update', $wholeGrade))->toBeFalse();
});

// ─── Registrul automat (observer): editarea = corecție consemnată ───────────────────────

it('editarea conținutului consemnează automat corecția aplicată — vechi → nou, cine, când', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    $homework = hwcAssignment($author);

    $this->actingAs($authorUser);
    $homework->update(['required_task' => 'Ex. 1-5 pagina 12']);

    $correction = HomeworkCorrection::query()->sole();

    expect($homework->refresh()->required_task)->toBe('Ex. 1-5 pagina 12')
        ->and($correction->old_required_task)->toBe('Ex. 1-3 pagina 10')
        ->and($correction->new_required_task)->toBe('Ex. 1-5 pagina 12')
        // Câmpul neatins NU intră în consemnare.
        ->and($correction->new_topic)->toBeNull()
        ->and($correction->status)->toBe(CorrectionStatus::Approved)
        ->and($correction->requested_by_user_id)->toBe($authorUser->id)
        ->and($correction->reviewed_by_user_id)->toBe($authorUser->id)
        ->and($correction->isDirect())->toBeTrue()
        // Corecția directă nu mai cere motivare.
        ->and($correction->reason)->toBeNull();
});

it('schimbarea câmpurilor ne-conținut (data lecției) NU aterizează în registru', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    $homework = hwcAssignment($author);

    $this->actingAs($authorUser);
    $homework->update(['assigned_on' => now()->addDay()->toDateString()]);

    expect(HomeworkCorrection::query()->count())->toBe(0);
});

it('editările din consolă (fără utilizator web) nu ating registrul — seed-erele se consemnează singure', function () {
    $homework = hwcAssignment();

    $homework->update(['topic' => 'Titlu schimbat din consolă']);

    expect(HomeworkCorrection::query()->count())->toBe(0);
});

// ─── Secțiunea: grup Catalog + EXCLUSIV personalul pedagogic (punctele 1–2) ─────────────

it('secțiunea stă în grupul Catalog, imediat sub Teme', function () {
    expect(HomeworkCorrectionResource::getNavigationGroup())->toBe(__('panel.nav.groups.catalog'))
        ->and(HomeworkCorrectionResource::getNavigationSort())->toBe(35);
});

it('matricea de acces: EXCLUSIV profesor/diriginte cu fișă — administrația nu mai are secțiunea', function (string $role, bool $needsTeacher, bool $allowed) {
    $user = hwcUser(UserRole::from($role));

    if ($needsTeacher) {
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    $response = $this->actingAs($user)->get('/admin/homework-corrections');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    // Registrul e al celor care corectează — administrația supraveghează prin Jurnalul de audit.
    'super-admin' => [UserRole::Admin->value, false, false],
    'director' => [UserRole::Director->value, false, false],
    'prim-vicedirector' => [UserRole::PrimVicedirector->value, false, false],
    'administrator operațional' => [UserRole::AdministratorOperational->value, false, false],
    'administrator tehnic' => [UserRole::AdministratorTehnic->value, false, false],
    'profesor cu fișă' => [UserRole::Profesor->value, true, true],
    'diriginte cu fișă' => [UserRole::Diriginte->value, true, true],
    'profesor fără fișă' => [UserRole::Profesor->value, false, false],
    'elev' => [UserRole::Elev->value, false, false],
    'părinte' => [UserRole::Parinte->value, false, false],
]);

it('vitrina multi-rol: în context Director secțiunea lipsește, în context Profesor apare', function () {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Director->value, UserRole::Profesor->value]);
    Teacher::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    session()->put(ActiveRole::SESSION_KEY, UserRole::Director->value);
    expect(HomeworkCorrectionResource::canViewAny())->toBeFalse();

    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);
    expect(HomeworkCorrectionResource::canViewAny())->toBeTrue();
});

// ─── Scoping-ul registrului ─────────────────────────────────────────────────────────────

it('profesorul vede corecțiile operate de el ȘI pe cele făcute de alții pe temele lui', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    [$strangerUser, $stranger] = hwcTeacherUser(UserRole::Profesor);
    [$homeroomUser] = hwcTeacherUser(UserRole::Diriginte);

    // A: corecția proprie; B: corecția dirigintelui pe tema lui A; C: corecția străinului pe tema lui.
    HomeworkCorrection::recordApplied(hwcAssignment($author), ['topic' => 'v'], ['topic' => 'n'], $authorUser->id);
    HomeworkCorrection::recordApplied(hwcAssignment($author), ['topic' => 'v'], ['topic' => 'n'], $homeroomUser->id);
    HomeworkCorrection::recordApplied(hwcAssignment($stranger), ['topic' => 'v'], ['topic' => 'n'], $strangerUser->id);

    $this->actingAs($authorUser);
    // Autorul vede că și dirigintele i-a atins tema — 2 rânduri, nu doar al lui.
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(2);

    $this->actingAs($strangerUser);
    expect(HomeworkCorrectionResource::getEloquentQuery()->count())->toBe(1);
});

it('dirigintele vede corecțiile pe temele clasei lui; fișa străină dă 404', function () {
    [$homeroomUser, $homeroom] = hwcTeacherUser(UserRole::Diriginte);
    SchoolClass::factory()->create(['grade_level' => 7, 'section' => 'A', 'homeroom_teacher_id' => $homeroom->id]);
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);

    $inClass = HomeworkCorrection::recordApplied(
        hwcAssignment($author, ['grade_level' => 7, 'section' => 'A']),
        ['topic' => 'v'], ['topic' => 'n'], $authorUser->id,
    );
    $outOfClass = HomeworkCorrection::recordApplied(
        hwcAssignment($author, ['grade_level' => 9, 'section' => 'B']),
        ['topic' => 'v'], ['topic' => 'n'], $authorUser->id,
    );

    $this->actingAs($homeroomUser);
    expect(HomeworkCorrectionResource::getEloquentQuery()->pluck('id')->all())->toBe([$inClass->id]);

    // Scoping-ul ASCUNDE rândurile străine → 404 (nici existența nu se confirmă).
    $this->get("/admin/homework-corrections/{$outOfClass->id}")->assertNotFound();
    $this->get("/admin/homework-corrections/{$inClass->id}")->assertOk();
});

// ─── Fișa corecției ─────────────────────────────────────────────────────────────────────

it('fișa corecției directe: vechi → nou + „Corecție aplicată", fără butoane de judecată', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    $homework = hwcAssignment($author);

    $this->actingAs($authorUser);
    $homework->update(['required_task' => 'Ex. 1-6 pagina 14']);
    $correction = HomeworkCorrection::query()->sole();

    $component = Livewire::test(ViewHomeworkCorrection::class, ['record' => $correction->id])
        ->assertSee('Ex. 1-3 pagina 10')
        ->assertSee('Ex. 1-6 pagina 14')
        ->assertSee(__('panel.homework_correction_view.applied_direct'));

    // Judecata a fost demontată: cronologia are O SINGURĂ intrare, fără acțiuni de verdict.
    expect($component->instance()->timeline())->toHaveCount(1)
        ->and($component->instance()->getCachedHeaderActions())->toBe([]);
});

it('rândurile istorice ale fluxului vechi își păstrează cronologia depunere → verdict', function () {
    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    $reviewer = hwcUser(UserRole::Director);

    $historic = HomeworkCorrection::factory()->create([
        'homework_assignment_id' => hwcAssignment($author)->id,
        'requested_by_user_id' => $authorUser->id,
        'new_required_task' => 'Propunerea de atunci',
        'status' => CorrectionStatus::Rejected,
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => now(),
        'review_note' => 'Verdictul de atunci.',
    ]);

    $this->actingAs($authorUser);

    $timeline = Livewire::test(ViewHomeworkCorrection::class, ['record' => $historic->id])
        ->instance()
        ->timeline();

    expect($timeline)->toHaveCount(2)
        ->and($timeline[1]['note'])->toBe('Verdictul de atunci.');
});

// ─── Igienă: purge demo + urmă în audit ─────────────────────────────────────────────────

it('purge-ul demo șterge corecțiile [DEMO] și temele-suport, dar nu atinge datele reale', function () {
    $teacher = Teacher::factory()->create();

    $demoHomework = HomeworkAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'topic' => '[DEMO] Fracții ordinare',
        'required_task' => 'Ex. 1-4',
    ]);
    HomeworkCorrection::recordApplied(
        $demoHomework, ['required_task' => 'Ex. 1-4'], ['required_task' => 'Ex. 1-6'],
        hwcUser(UserRole::Profesor)->id, '[DEMO] motiv de test',
    );

    $realHomework = hwcAssignment($teacher);
    $realCorrection = HomeworkCorrection::recordApplied(
        $realHomework, ['topic' => 'Tema veche'], ['topic' => 'Tema corectată'],
        hwcUser(UserRole::Profesor)->id, 'greșeală reală de redactare',
    );

    $this->artisan('app:purge-demo-data')->assertSuccessful();

    expect(HomeworkAssignment::withTrashed()->whereKey($demoHomework->id)->exists())->toBeFalse()
        ->and(HomeworkCorrection::query()->where('reason', 'like', '[DEMO]%')->exists())->toBeFalse()
        ->and(HomeworkAssignment::query()->whereKey($realHomework->id)->exists())->toBeTrue()
        ->and(HomeworkCorrection::query()->whereKey($realCorrection->id)->exists())->toBeTrue();
});

it('corecția directă lasă urmă dublă: registrul (created) + auditul temei (updated)', function () {
    config(['audit.console' => true]);

    [$authorUser, $author] = hwcTeacherUser(UserRole::Profesor);
    $homework = hwcAssignment($author);

    $this->actingAs($authorUser);
    $homework->update(['topic' => 'Titlu auditat']);

    $correction = HomeworkCorrection::query()->sole();

    expect(DB::table('audits')
        ->where('auditable_type', HomeworkCorrection::class)
        ->where('auditable_id', $correction->id)
        ->where('event', 'created')
        ->exists())->toBeTrue()
        ->and(DB::table('audits')
            ->where('auditable_type', HomeworkAssignment::class)
            ->where('auditable_id', $homework->id)
            ->where('event', 'updated')
            ->exists())->toBeTrue();
});
