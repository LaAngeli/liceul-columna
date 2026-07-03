<?php

namespace App\Observers;

use App\Enums\AcademicRecordPeriod;
use App\Models\AcademicRecord;
use App\Models\CorigentaExam;
use App\Models\Enrollment;

/**
 * La introducerea notei de corigență, aceasta devine rezultatul oficial al disciplinei pe anul
 * respectiv (§2.5) → se scrie în foaia matricolă ca medie ANUALĂ a treptei curente a elevului.
 * Fără notă (doar programare/comisie) nu se scrie nimic. Treapta se ia din înmatricularea elevului
 * în anul examenului; dacă nu poate fi determinată, se sare (nota rămâne pe examen).
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
        $enrollment = Enrollment::query()
            ->with('schoolClass')
            ->where('student_id', $exam->student_id)
            ->where('academic_year_id', $exam->term->academic_year_id)
            ->first();

        if ($enrollment === null) {
            return null;
        }

        return $enrollment->schoolClass->grade_level;
    }
}
