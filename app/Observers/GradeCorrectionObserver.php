<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\GradeCorrection;
use App\Notifications\CatalogNotification;
use Illuminate\Validation\ValidationException;

/**
 * La o cerere NOUĂ de corecție de notă, anunță aprobatorii (prim-vicedirector + director, plus
 * super-admin) — „pe nișa lor" (spec §3.1 / §5). La APROBARE/RESPINGERE, anunță familia elevului
 * că statutul corecției s-a schimbat (bucla de feedback, tipul StatusChange).
 */
class GradeCorrectionObserver
{
    public function __construct(
        private NotifyStaff $notifier,
        private NotifyStudentFamily $family,
    ) {}

    /**
     * Invariant: o notă nu poate avea două cereri de corecție în așteptare simultan (administrația
     * ar judeca două propuneri de valoare pentru aceeași notă). UI-ul ascunde deja acțiunea, dar
     * regula trăiește aici, unde nicio cale — seeder, import, API viitor — nu o poate ocoli.
     */
    public function creating(GradeCorrection $correction): void
    {
        if ($correction->status !== CorrectionStatus::Pending) {
            return;
        }

        $exists = GradeCorrection::query()
            ->where('grade_id', $correction->grade_id)
            ->where('status', CorrectionStatus::Pending)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'grade_id' => __('panel.actions.request_correction.already_pending'),
            ]);
        }
    }

    public function created(GradeCorrection $correction): void
    {
        $this->notifier->byRole(
            [
                UserRole::Admin->value,
                UserRole::Director->value,
                UserRole::PrimVicedirector->value,
            ],
            new CatalogNotification(
                NotificationType::GradeCorrectionRequest,
                [
                    'teacher' => $correction->requestedBy->name,
                    'student' => $correction->grade->student->full_name,
                ],
            ),
        );
    }

    public function updated(GradeCorrection $correction): void
    {
        if (! $correction->wasChanged('status')
            || ! in_array($correction->status, [CorrectionStatus::Approved, CorrectionStatus::Rejected], true)) {
            return;
        }

        $student = $correction->grade?->student;

        if ($student === null) {
            return;
        }

        $this->family->send($student, new CatalogNotification(
            NotificationType::StatusChange,
            ['student' => $student->full_name, 'status' => $correction->status->getLabel()],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }
}
