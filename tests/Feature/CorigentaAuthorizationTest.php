<?php

/**
 * Gardarea modulului de CORIGENȚĂ (LOT 2 al restructurării „Configurare").
 *
 * Cele trei modele nu aveau NICIO policy — iar Filament v4, în mod ne-strict, autorizează implicit
 * ce nu e refuzat explicit: `canEdit`/`canDelete` cădeau pe „permis" pentru ORICE rol, pe orice
 * cale care nu trece prin `canAccess()` al resursei (RelationManager, acțiune montată, API viitor).
 *
 * În plus, §3.2 îi interzice expres administratorului operațional introducerea de note — dar el
 * putea consemna nota de corigență, care devine media finală a disciplinei în foaia matricolă.
 */

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\ExamCommission;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create();
    $subject = Subject::factory()->create();

    $this->session = CorigentaSession::create([
        'academic_year_id' => $year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2026-06-15',
        'ends_on' => '2026-06-25',
        'status' => CorigentaSessionStatus::Draft,
    ]);

    $this->commission = ExamCommission::create([
        'academic_year_id' => $year->id,
        'subject_id' => $subject->id,
        'name' => 'Comisia de matematică',
    ]);

    $this->exam = CorigentaExam::create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'season' => CorigentaSeason::Vara,
        'corigenta_session_id' => $this->session->id,
    ]);
});

function corigentaUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('rolurile fără drept nu pot edita sau șterge NICIUNA din entitățile de corigență', function (string $role) {
    $user = corigentaUser(UserRole::from($role));

    foreach ([$this->session, $this->commission, $this->exam] as $record) {
        expect($user->can('view', $record))->toBeFalse()
            ->and($user->can('update', $record))->toBeFalse()
            ->and($user->can('delete', $record))->toBeFalse();
    }
})->with([
    UserRole::Parinte->value,
    UserRole::Elev->value,
    UserRole::Profesor->value,
    UserRole::Diriginte->value,
    UserRole::AdministratorTehnic->value,
]);

it('conducerea administrează corigența (sesiuni, comisii, programare)', function (string $role) {
    $user = corigentaUser(UserRole::from($role));

    expect($user->can('update', $this->session))->toBeTrue()
        ->and($user->can('update', $this->commission))->toBeTrue()
        ->and($user->can('update', $this->exam))->toBeTrue();
})->with([
    UserRole::Director->value,
    UserRole::PrimVicedirector->value,
    UserRole::AdministratorOperational->value,
]);

it('examenul NU se creează niciodată din formular — rândurile se generează', function () {
    foreach ([UserRole::Admin, UserRole::Director, UserRole::AdministratorOperational] as $role) {
        expect(corigentaUser($role)->can('create', CorigentaExam::class))->toBeFalse();
    }
});

it('examenul cu notă consemnată e istoric: nu se mai șterge; cel neefectuat, da', function () {
    $director = corigentaUser(UserRole::Director);

    expect($director->can('delete', $this->exam))->toBeTrue();

    $this->exam->update(['mark' => 7.5]);

    expect($director->refresh()->can('delete', $this->exam->refresh()))->toBeFalse();
});

it('administratorul operațional programează examenul, dar NU consemnează nota (§3.2)', function () {
    $ao = corigentaUser(UserRole::AdministratorOperational);

    // Poate programa (câmpurile de configurare).
    expect($ao->can('update', $this->exam))->toBeTrue()
        // Dar nu are dreptul academic care deschide câmpul `mark` din formular.
        ->and($ao->canAdministerCatalog())->toBeFalse();

    // Autoritatea academică îl are.
    expect(corigentaUser(UserRole::PrimVicedirector)->canAdministerCatalog())->toBeTrue();
});

it('comisia de examen e auditabilă, ca sesiunea și examenul', function () {
    expect($this->commission)->toBeInstanceOf(Auditable::class);

    // Auditarea e oprită în consolă implicit (config `audit.console`) — o pornim doar pentru
    // acțiunea testată, ca în restul suitei.
    config(['audit.console' => true]);

    $this->commission->update(['name' => 'Comisia rescrisă']);

    expect($this->commission->audits()->where('event', 'updated')->exists())->toBeTrue();
});
