<?php

namespace App\Observers;

use App\Enums\CorrectionStatus;
use App\Models\HomeworkCorrection;
use Illuminate\Validation\ValidationException;

/**
 * Invariante ale corecțiilor de teme. Semnalul pentru aprobatori este badge-ul „în așteptare"
 * de pe resursa „Corecții teme" (fără notificări push în v1 — temele nu ating familia până la
 * aprobare, iar coada e vizitată zilnic de administrație).
 */
class HomeworkCorrectionObserver
{
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
}
