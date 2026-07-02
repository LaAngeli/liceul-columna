<?php

namespace App\Support;

use App\Enums\EvaluationType;
use App\Models\Grade;
use App\Models\SummativeDesignation;
use Illuminate\Support\Collection;

/**
 * Interogări pe designarea disciplinelor cu sumativă (ESS/teză) pe clasă (§1.3). Sursă unică pentru
 * garda de introducere (GradeObserver) și pentru semnalarea tezelor lipsă (diriginte/management).
 */
final class Summatives
{
    /**
     * Disciplina e designată cu sumativă la clasa dată?
     */
    public static function isDesignated(int $subjectId, int $schoolClassId): bool
    {
        return SummativeDesignation::query()
            ->where('subject_id', $subjectId)
            ->where('school_class_id', $schoolClassId)
            ->exists();
    }

    /**
     * Clasa are cel puțin o disciplină designată (deci garda de introducere e activă pentru ea)?
     */
    public static function classIsConfigured(int $schoolClassId): bool
    {
        return SummativeDesignation::query()
            ->where('school_class_id', $schoolClassId)
            ->exists();
    }

    /**
     * Disciplinele designate la clasă la care elevul NU are (încă) o notă sumativă activă în semestru
     * — semnal de calitate „teze lipsă".
     *
     * @return Collection<int, SummativeDesignation>
     */
    public static function missingForStudentTerm(int $studentId, int $schoolClassId, int $termId): Collection
    {
        $designations = SummativeDesignation::query()
            ->with('subject')
            ->where('school_class_id', $schoolClassId)
            ->get();

        if ($designations->isEmpty()) {
            return collect();
        }

        $subjectsWithSummative = Grade::query()
            ->active()
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->where('evaluation_type', EvaluationType::Teza->value)
            ->whereNotNull('value')
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $designations
            ->reject(fn (SummativeDesignation $designation): bool => in_array((int) $designation->subject_id, $subjectsWithSummative, true))
            ->values();
    }
}
