<?php

namespace App\Actions;

use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\TermAverage;

/**
 * Calculează media semestrială (MS) pentru (elev, disciplină, semestru) după regulile
 * pe cicluri (§2.4): gimnaziu/liceu cu teză → MS=(MC+teză)/2; altfel MS=MC. Sutimi,
 * FĂRĂ rotunjire (trunchiere). Persistă rezultatul în `term_averages` (cache).
 * MC = media aritmetică a notelor CURENTE (curentă + ESI); teza e ponderată separat 50%.
 */
class ComputeTermAverage
{
    public function execute(int $studentId, int $subjectId, int $termId): ?TermAverage
    {
        $grades = Grade::query()
            ->active() // notele anulate nu contează la medie
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->whereNotNull('value')
            ->get(['school_class_id', 'evaluation_type', 'value']);

        if ($grades->isEmpty()) {
            TermAverage::query()
                ->where('student_id', $studentId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->delete();

            return null;
        }

        $schoolClassId = (int) $grades->first()->school_class_id;
        $gradeLevel = (int) (SchoolClass::query()->whereKey($schoolClassId)->value('grade_level') ?? 0);

        $current = $grades->filter(fn (Grade $g): bool => $g->evaluation_type->countsAsCurrent());
        $tezaGrade = $grades->first(fn (Grade $g): bool => $g->evaluation_type === EvaluationType::Teza);

        $mc = $current->isNotEmpty()
            ? (float) $current->avg(fn (Grade $g): float => (float) $g->value)
            : null;
        $teza = $tezaGrade !== null ? (float) $tezaGrade->value : null;

        $ms = $this->semesterAverage(SchoolCycle::fromGradeLevel($gradeLevel), $mc, $teza);

        if ($ms === null) {
            return null;
        }

        return TermAverage::updateOrCreate(
            ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
            ['school_class_id' => $schoolClassId, 'value' => $ms],
        );
    }

    /**
     * Primar: MS = MC (media notelor curente). Gimnaziu/Liceu: teza ponderată 50%.
     */
    private function semesterAverage(SchoolCycle $cycle, ?float $mc, ?float $teza): ?float
    {
        if ($cycle === SchoolCycle::Primar) {
            return $mc !== null ? $this->truncate2($mc) : null;
        }

        if ($mc !== null && $teza !== null) {
            return $this->truncate2(($mc + $teza) / 2);
        }

        if ($teza !== null) {
            return $this->truncate2($teza);
        }

        return $mc !== null ? $this->truncate2($mc) : null;
    }

    /**
     * Trunchiere la 2 zecimale, fără rotunjire (8,567 → 8,56). Epsilonul compensează
     * eroarea de reprezentare în virgulă mobilă, fără a afecta granularitatea notelor.
     */
    private function truncate2(float $value): float
    {
        return floor(($value + 1e-9) * 100) / 100;
    }
}
