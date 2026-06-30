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
 * Lot 8.C / audit #2: la finalizarea (aprobare/respingere) unei cereri depuse de familie,
 * familia primește o notificare StatusChange (bucla de feedback închisă).
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

it('aprobarea unei motivări notifică familia (StatusChange)', function () {
    Notification::fake();
    $student = studentWithFamily();
    $reviewer = User::factory()->create();

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    $motivation->approve($reviewer->id, null);

    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
    );
});

it('respingerea unei corecții de notă notifică familia', function () {
    Notification::fake();
    $student = studentWithFamily();
    $reviewer = User::factory()->create();

    $grade = Grade::factory()->create(['student_id' => $student->id]);
    $correction = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'status' => CorrectionStatus::Pending,
    ]);

    $correction->reject($reviewer->id, 'nejustificat');

    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
    );
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

    Notification::assertSentTo(
        $student->user,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
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
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
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
