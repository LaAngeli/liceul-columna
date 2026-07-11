<?php

namespace App\Observers;

use App\Enums\AcademicRecordPeriod;
use App\Models\AcademicRecord;
use App\Models\CorigentaExam;
use App\Models\Enrollment;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * La introducerea notei de corigență, aceasta devine rezultatul oficial al disciplinei pe anul
 * respectiv (§2.5) → se scrie în foaia matricolă ca medie ANUALĂ a treptei curente a elevului.
 * Fără notă (doar programare/comisie) nu se scrie nimic. Treapta se ia din înmatricularea elevului
 * în anul examenului (inclusiv una arhivată — transferul nu schimbă treapta istorică); dacă lipsește
 * cu totul, nota rămâne DOAR pe examen, iar operatorul e AVERTIZAT — arhiva oficială nu are voie să
 * rămână incompletă în tăcere.
 */
class CorigentaExamObserver
{
    public function saved(CorigentaExam $exam): void
    {
        if ($exam->mark === null) {
            return;
        }

        $gradeLevel = $this->currentGradeLevel($exam);

        if ($gradeLevel === null) {
            $this->warnNotArchived($exam);

            return;
        }

        AcademicRecord::updateOrCreate(
            [
                'student_id' => $exam->student_id,
                'subject_id' => $exam->subject_id,
                'grade_level' => $gradeLevel,
                'period' => AcademicRecordPeriod::Annual,
            ],
            ['value' => $exam->mark],
        );
    }

    private function currentGradeLevel(CorigentaExam $exam): ?int
    {
        // withTrashed pe query + relația schoolClass (deja withTrashed): o înmatriculare sau o
        // clasă arhivată încă spune corect TREAPTA la care s-a dat examenul.
        $enrollment = Enrollment::withTrashed()
            ->with('schoolClass')
            ->where('student_id', $exam->student_id)
            ->where('academic_year_id', $exam->term->academic_year_id)
            ->first();

        return $enrollment?->schoolClass?->grade_level;
    }

    /**
     * Nota a fost salvată pe examen, dar NU a putut fi scrisă în foaia matricolă. Jurnalizăm
     * mereu; dacă salvarea vine din panou (sesiune activă), operatorul vede și un avertisment —
     * altfel ar primi doar confirmarea de succes a salvării examenului.
     */
    private function warnNotArchived(CorigentaExam $exam): void
    {
        Log::warning('Nota de corigență nu a fost scrisă în foaia matricolă: elevul nu are înmatriculare în anul examenului.', [
            'corigenta_exam_id' => $exam->id,
            'student_id' => $exam->student_id,
            'subject_id' => $exam->subject_id,
            'term_id' => $exam->term_id,
        ]);

        if (auth('web')->check()) {
            Notification::make()
                ->warning()
                ->title(__('panel.corigenta.not_archived_title'))
                ->body(__('panel.corigenta.not_archived_body'))
                ->persistent()
                ->send();
        }
    }
}
