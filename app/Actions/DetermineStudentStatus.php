<?php

namespace App\Actions;

use App\Enums\StudentStatus;
use App\Models\TermAverage;

/**
 * Determină statutul PRELIMINAR al elevului pentru un semestru, din mediile calculate (§2.5):
 * corigent dacă o medie < 5; altfel promovat. Statutul oficial e validat de Consiliul profesoral
 * + ordinul directorului (pas administrativ ulterior); aici e situația curentă din catalog.
 */
class DetermineStudentStatus
{
    private const PASS = 5.0;

    /**
     * @return array{status: StudentStatus|null, failingSubjects: array<int, string>, average: float|null}
     */
    public function forTerm(int $studentId, int $termId): array
    {
        $averages = TermAverage::query()
            ->with('subject')
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->get();

        if ($averages->isEmpty()) {
            return ['status' => null, 'failingSubjects' => [], 'average' => null];
        }

        $failingSubjects = $averages
            ->filter(fn (TermAverage $average): bool => (float) $average->value < self::PASS)
            ->map(fn (TermAverage $average): string => $average->subject->name)
            ->values()
            ->all();

        return [
            'status' => $failingSubjects !== [] ? StudentStatus::Corigent : StudentStatus::Promovat,
            'failingSubjects' => $failingSubjects,
            'average' => round($averages->avg(fn (TermAverage $average): float => (float) $average->value), 2),
        ];
    }
}
