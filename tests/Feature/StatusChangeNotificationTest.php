<?php

use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AbsenceMotivation;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Lot 8.C / audit #2: la finalizarea (aprobare/respingere) unei cereri depuse de FAMILIE
 * (motivare, cerere de document), familia primește o notificare StatusChange (bucla de feedback).
 * EXCEPȚIE — corecțiile de notă NU sunt cereri ale familiei (teacher↔conducere): verdictul lor
 * merge la solicitant (respingere) / familia află „notă corectată" (aprobare) — vezi cluster Corecții.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function studentWithFamily(): Student
{
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);

    return Student::factory()->create(['user_id' => $elev->id]);
}

it('aprobarea unei motivări notifică familia (verdict dedicat)', function () {
    Notification::fake();
    $student = studentWithFamily();
    $reviewer = User::factory()->create();

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    $motivation->approve($reviewer->id, null);

    // Tip DEDICAT (2026-07-20): verdictul motivării nu mai folosește StatusChange generic —
    // notificarea numește cererea și perioada; motivul respingerii rămâne DOAR în cabinet.
    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::AbsenceMotivationDecided,
    );
});

it('respingerea unei corecții de notă notifică SOLICITANTUL, nu familia', function () {
    $student = studentWithFamily();
    $reviewer = User::factory()->create();
    $requester = User::factory()->create();
    $requester->assignRole(UserRole::Profesor->value);

    $grade = Grade::factory()->create(['student_id' => $student->id]);
    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => $requester->id,
        'status' => CorrectionStatus::Pending,
    ]);

    // Interceptăm ABIA acum, ca să izolăm respingerea (nu notificarea de notă nouă din setup).
    Notification::fake();
    $correction->reject($reviewer->id, 'nejustificat');

    // Solicitantul (profesorul) află verdictul + are motivul în arhivă.
    Notification::assertSentTo(
        $requester,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::GradeCorrectionRejected,
    );
    // Familia n-a fost implicată și nota n-a fost atinsă → fără zgomot pentru ea.
    Notification::assertNotSentTo($student->user, CatalogNotification::class);
});

it('procesarea unei cereri de document notifică familia', function () {
    Notification::fake();
    $student = studentWithFamily();
    $reviewer = User::factory()->create();

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    $request->markProcessed($reviewer->id);

    // Tip DEDICAT (2026-07-17): închiderea cererii nu mai folosește StatusChange generic —
    // notificarea numește TIPUL cererii și decizia, cu link direct pe tabul Cereri.
    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::DocumentRequestClosed,
    );
});

it('respingerea unei cereri de document o trece în Rejected și notifică familia', function () {
    Notification::fake();
    $student = studentWithFamily();
    $reviewer = User::factory()->create();

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    $request->markRejected($reviewer->id, 'date incomplete');

    expect($request->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($request->fresh()->review_note)->toBe('date incomplete');

    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::DocumentRequestClosed,
    );
});

it('o cerere rămasă în așteptare NU notifică familia', function () {
    Notification::fake();
    $student = studentWithFamily();

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    // o actualizare care nu schimbă statusul
    $motivation->update(['reason' => 'text nou']);

    Notification::assertNotSentTo($student->user, CatalogNotification::class);
});
