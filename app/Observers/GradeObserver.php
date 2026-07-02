<?php

namespace App\Observers;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Jobs\RecomputeTermAverage;
use App\Models\Grade;
use App\Notifications\CatalogNotification;
use App\Support\Summatives;
use Illuminate\Validation\ValidationException;

/**
 * Recalculează media semestrială (cache în term_averages) la fiecare schimbare a unei
 * note din panou și notifică familia la o notă NOUĂ (spec §5). Recalculul pleacă pe coadă
 * ({@see RecomputeTermAverage}), ca salvarea să nu aștepte calculul. Importul legacy folosește
 * query builder (fără evenimente Eloquent), deci nu declanșează nici recalculul, nici notificarea.
 */
class GradeObserver
{
    public function __construct(
        private NotifyStudentFamily $notifier,
    ) {}

    /**
     * Gardă „sumativă doar pe disciplină designată" (§1.3): dacă o clasă are configurate discipline
     * cu sumativă (prin ordin), nu se poate introduce o notă sumativă (ESS/teză) pe o disciplină
     * nedesignată. Clasele neconfigurate (fără nicio designare) NU sunt restricționate (date legacy
     * sau încă neconfigurate). Importul legacy folosește query builder → nu trece prin acest eveniment.
     */
    public function creating(Grade $grade): void
    {
        if (! $grade->evaluation_type->isWeighted()) {
            return;
        }

        $schoolClassId = (int) $grade->school_class_id;

        if (Summatives::classIsConfigured($schoolClassId)
            && ! Summatives::isDesignated((int) $grade->subject_id, $schoolClassId)) {
            throw ValidationException::withMessages([
                'evaluation_type' => __('grading.summative.not_designated'),
            ]);
        }
    }

    public function created(Grade $grade): void
    {
        $student = $grade->student;

        if ($student === null) {
            return;
        }

        $this->notifier->send($student, new CatalogNotification(
            NotificationType::NewGrade,
            [
                'student' => $student->full_name,
                'subject' => $grade->subject->name,
            ],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }

    public function saved(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function deleted(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function restored(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function forceDeleted(Grade $grade): void
    {
        $this->recompute($grade);
    }

    private function recompute(Grade $grade): void
    {
        RecomputeTermAverage::dispatch(
            (int) $grade->student_id,
            (int) $grade->subject_id,
            (int) $grade->term_id,
        );
    }
}
