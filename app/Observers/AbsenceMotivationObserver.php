<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Enums\NotificationType;
use App\Models\AbsenceMotivation;
use App\Notifications\CatalogNotification;

/**
 * La o cerere NOUĂ de motivare a absențelor, anunță dirigintele clasei elevului (cel care
 * validează) — „pe nișa lui" (spec §2.1 / §5).
 */
class AbsenceMotivationObserver
{
    public function __construct(private NotifyStaff $notifier) {}

    public function created(AbsenceMotivation $motivation): void
    {
        $student = $motivation->student;

        if ($student === null) {
            return;
        }

        $this->notifier->toUser(
            $student->homeroomUser(),
            new CatalogNotification(
                NotificationType::AbsenceMotivationSubmitted,
                ['student' => $student->full_name],
            ),
        );
    }
}
