<?php

namespace App\Actions;

use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\TermAverage;
use App\Support\Grades;

/**
 * Calculează media semestrială (MS) pentru (elev, disciplină, semestru) după regulile pe cicluri
 * (§1.3): gimnaziu/liceu cu sumativă → MS = MC·(1−pondere) + sumativă·pondere (pondere din tip_nota,
 * 0,50 → (MC+sumativă)/2); primar → MS = MC. Sutimi, FĂRĂ rotunjire (trunchiere). Persistă MS și
 * componentele (MC, sumativă) în `term_averages` (cache). MC = media notelor curente (curentă + ESI);
 * sumativa (ESS/teză) e media notelor sumative semestriale, ponderată separat.
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

        $cycle = SchoolCycle::fromGradeLevel($gradeLevel);

        $current = $grades->filter(fn (Grade $g): bool => $g->evaluation_type->countsAsCurrent());
        $weighted = $grades->filter(fn (Grade $g): bool => $g->evaluation_type->isWeighted());

        $mc = $current->isNotEmpty()
            ? Grades::truncate2((float) $current->avg(fn (Grade $g): float => (float) $g->value))
            : null;

        // Sumativa semestrială (ESS/teză) există doar la gimnaziu/liceu; primarul nu are sumativă.
        // Mai multe sumative → media lor (nu se pierde nici una, spre deosebire de „prima teză").
        $summative = ($cycle !== SchoolCycle::Primar && $weighted->isNotEmpty())
            ? Grades::truncate2((float) $weighted->avg(fn (Grade $g): float => (float) $g->value))
            : null;

        $ms = $this->semesterAverage($cycle, $mc, $summative);

        if ($ms === null) {
            return null;
        }

        return TermAverage::updateOrCreate(
            ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
            [
                'school_class_id' => $schoolClassId,
                'value' => $ms,
                'mc_value' => $mc,
                'summative_value' => $summative,
            ],
        );
    }

    /**
     * Primar: MS = MC (fără sumativă). Gimnaziu/Liceu: MS = MC·(1−pondere) + sumativă·pondere
     * (pondere din tip_nota, 0,50 → (MC+sumativă)/2), când există ambele; altfel componenta
     * prezentă. MC și sumativa vin deja trunchiate la sutimi; promovarea se decide pe MS (§3).
     */
    private function semesterAverage(SchoolCycle $cycle, ?float $mc, ?float $summative): ?float
    {
        if ($cycle === SchoolCycle::Primar) {
            return $mc;
        }

        if ($mc !== null && $summative !== null) {
            $weight = EvaluationType::Teza->weight() ?? 0.5;

            return Grades::truncate2($mc * (1 - $weight) + $summative * $weight);
        }

        return $summative ?? $mc;
    }
}
