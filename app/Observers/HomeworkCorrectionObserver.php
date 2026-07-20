<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Models\HomeworkCorrection;
use App\Notifications\CatalogNotification;
use App\Support\ContentTranslator;
use Illuminate\Validation\ValidationException;

/**
 * Invariante + notificări ale corecțiilor de teme. Semnalul pentru APROBATORI rămâne badge-ul
 * „în așteptare" de pe resursă (coada e vizitată zilnic); SOLICITANTUL însă primește verdictul
 * de RESPINGERE ca notificare — altfel redepune orbește. Simetric cu corecțiile de notă:
 * aprobarea nu se notifică separat, e vizibilă prin efect (tema chiar se schimbă).
 */
class HomeworkCorrectionObserver
{
    public function __construct(private NotifyStaff $notifier) {}

    /**
     * O temă nu poate avea două cereri de corecție în așteptare simultan (administrația ar judeca
     * două propuneri concurente pentru același conținut). UI-ul ascunde deja acțiunea, dar regula
     * trăiește aici, unde nicio cale — seeder, import, API viitor — nu o poate ocoli.
     */
    public function creating(HomeworkCorrection $correction): void
    {
        if ($correction->status !== CorrectionStatus::Pending) {
            return;
        }

        $exists = HomeworkCorrection::query()
            ->where('homework_assignment_id', $correction->homework_assignment_id)
            ->where('status', CorrectionStatus::Pending)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'homework_assignment_id' => __('panel.actions.homework_correction.already_pending'),
            ]);
        }
    }

    /**
     * La trecerea în RESPINSĂ, solicitantul află verdictul + unde e motivul (fișa cererii).
     */
    public function updated(HomeworkCorrection $correction): void
    {
        if (! $correction->wasChanged('status') || $correction->status !== CorrectionStatus::Rejected) {
            return;
        }

        $subject = $correction->homeworkAssignment?->subject_name;

        $this->notifier->toUser(
            $correction->requestedBy,
            new CatalogNotification(
                NotificationType::HomeworkCorrectionRejected,
                ['subject' => $subject !== null ? ContentTranslator::subject($subject) : '—'],
                HomeworkCorrectionResource::getUrl('view', ['record' => $correction]),
            ),
        );
    }
}
