<?php

namespace App\Actions;

use App\Enums\StudentStatus;
use App\Models\CorigentaExam;
use App\Models\TermAverage;
use App\Support\Grades;

/**
 * Determină statutul elevului pentru un semestru din mediile calculate + rezultatele corigenței
 * (§2.5): promovat (toate mediile ≥ 5 SAU corigențele trecute), corigent (mai are corigențe de dat),
 * repetent (toate corigențele date, cel puțin una picată). Statutul OFICIAL rămâne validat
 * administrativ (Consiliul profesoral + ordin); aici e situația curentă din catalog.
 */
class DetermineStudentStatus
{
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

        $average = Grades::truncate2((float) $averages->avg(fn (TermAverage $ta): float => (float) $ta->value));
        $failing = $averages->filter(fn (TermAverage $ta): bool => $ta->isFailing());

        if ($failing->isEmpty()) {
            return ['status' => StudentStatus::Promovat, 'failingSubjects' => [], 'average' => $average];
        }

        // Disciplinele restante se rezolvă prin corigență (§2.5): trecută → lichidată; picată →
        // repetent; neexaminată → încă restantă. „Repetent" doar când TOATE corigențele sunt date
        // și cel puțin una e picată; cât timp mai sunt corigențe nedate, elevul rămâne „corigent".
        $examResults = CorigentaExam::query()
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->get()
            ->keyBy('subject_id');

        $unresolved = [];
        $anyPending = false;

        foreach ($failing as $ta) {
            $passed = $examResults->get($ta->subject_id)?->isPassed();

            if ($passed === true) {
                continue;
            }

            $unresolved[] = $ta->subject->name;
            $anyPending = $anyPending || $passed === null;
        }

        $status = match (true) {
            $unresolved === [] => StudentStatus::Promovat,
            $anyPending => StudentStatus::Corigent,
            default => StudentStatus::Repetent,
        };

        return ['status' => $status, 'failingSubjects' => $unresolved, 'average' => $average];
    }
}
