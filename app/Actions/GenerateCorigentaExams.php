<?php

namespace App\Actions;

use App\Enums\CorigentaSeason;
use App\Models\CorigentaExam;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;

/**
 * Generează automat intrările de corigență (spec §2.5 / #33) la marcarea elevului ca „corigent":
 * câte o intrare per disciplină restantă (medie < 5), idempotent. Data + comisia se completează
 * ulterior, când sesiunea de corigență e configurată/publicată. Sezon: sem. I → iarnă, sem. II → vară.
 */
class GenerateCorigentaExams
{
    public function forStudentTerm(Student $student, Term $term): int
    {
        $season = $term->number === 1 ? CorigentaSeason::Iarna : CorigentaSeason::Vara;

        $failing = TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->get()
            ->filter(fn (TermAverage $average): bool => $average->isFailing());

        $created = 0;

        foreach ($failing as $average) {
            CorigentaExam::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_id' => $average->subject_id,
                    'term_id' => $term->id,
                ],
                ['season' => $season],
            );
            $created++;
        }

        return $created;
    }
}
