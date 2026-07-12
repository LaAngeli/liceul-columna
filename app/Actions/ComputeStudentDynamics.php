<?php

namespace App\Actions;

use App\Enums\AcademicRecordPeriod;
use App\Models\AcademicRecord;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\ContentTranslator;
use App\Support\Grades;
use Illuminate\Support\Collection;

/**
 * Dinamica multi-an a unui elev (spec §2.2/§2.3): evoluția mediei generale și pe discipline
 * de la o treaptă la alta, tendința (creștere/stabil/scădere), poziționarea mediei curente față
 * de istoricul PROPRIU (nu față de alți elevi) și alerta timpurie la scădere semnificativă.
 *
 * Sursa istorică = foaia matricolă (academic_records, mediile ANUALE pe treaptă); prezentul =
 * mediile semestriale calculate (term_averages) pentru semestrul curent.
 */
class ComputeStudentDynamics
{
    /** Pragul (puncte) peste care o diferență de medie contează ca tendință. */
    private const TREND_THRESHOLD = 0.25;

    /** Scăderea față de media istorică proprie care declanșează alerta timpurie. */
    private const ALERT_DROP = 0.5;

    /**
     * @return array{
     *   general: list<array{level: int, average: float}>,
     *   subjects: list<array{subject: string, points: list<array{level: int, value: float}>, trend: string|null}>,
     *   current: array{average: float|null, historyAverage: float|null, previousYearSameTerm: float|null, trend: string|null, alert: bool}
     * }
     */
    public function for(Student $student): array
    {
        /** @var Collection<int, AcademicRecord> $records */
        $records = $student->academicRecords()
            ->where('period', AcademicRecordPeriod::Annual)
            ->whereNotNull('value')
            ->with('subject')
            ->get();

        $general = [];
        foreach ($records->groupBy('grade_level') as $level => $rows) {
            $general[] = [
                'level' => (int) $level,
                'average' => Grades::truncate2((float) $rows->avg(fn (AcademicRecord $r): float => (float) $r->value)),
            ];
        }
        usort($general, fn (array $a, array $b): int => $a['level'] <=> $b['level']);

        $subjects = [];
        foreach ($records->groupBy(fn (AcademicRecord $r): string => $r->subject->name) as $name => $rows) {
            $points = [];
            $values = [];
            foreach ($rows->sortBy('grade_level') as $record) {
                $value = Grades::truncate2((float) $record->value);
                $points[] = ['level' => (int) $record->grade_level, 'value' => $value];
                $values[] = $value;
            }

            $subjects[] = [
                'subject' => ContentTranslator::subject((string) $name),
                'points' => $points,
                'trend' => $this->trendOf($values),
            ];
        }
        usort($subjects, fn (array $a, array $b): int => strcmp($a['subject'], $b['subject']));

        $currentAverage = $this->currentGeneralAverage($student);
        $historyAverage = $general === []
            ? null
            : Grades::truncate2(array_sum(array_column($general, 'average')) / count($general));
        $lastAnnual = $general === [] ? null : $general[count($general) - 1]['average'];

        $trend = ($currentAverage !== null && $lastAnnual !== null)
            ? $this->trendBetween($lastAnnual, $currentAverage)
            : null;

        $alert = $currentAverage !== null
            && $historyAverage !== null
            && $currentAverage < $historyAverage - self::ALERT_DROP;

        return [
            'general' => $general,
            'subjects' => $subjects,
            'current' => [
                'average' => $currentAverage,
                'historyAverage' => $historyAverage,
                'previousYearSameTerm' => $this->previousYearSameTermAverage($student),
                'trend' => $trend,
                'alert' => $alert,
            ],
        ];
    }

    /**
     * Versiune UȘOARĂ folosită în cockpit (per copil): calculează DOAR tendința — ultima medie anuală din
     * foaia matricolă vs media curentă (semestru curent). Doar 2 query-uri/copil (vs. ~6 pentru `for()`).
     * Folosește o valoare pre-cached pentru `currentTermId` ca să elimini și acel query repetat.
     */
    public function trendFor(Student $student, ?int $currentTermId = null): ?string
    {
        $currentTermId ??= Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return null;
        }

        $currentAvg = TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->avg('value');

        if ($currentAvg === null) {
            return null;
        }

        $lastAnnual = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('period', AcademicRecordPeriod::Annual)
            ->whereNotNull('value')
            ->orderByDesc('grade_level')
            ->value('value');

        if ($lastAnnual === null) {
            return null;
        }

        return $this->trendBetween((float) $lastAnnual, (float) $currentAvg);
    }

    /**
     * Media generală curentă = media mediilor semestriale calculate (term_averages) la semestrul curent.
     */
    private function currentGeneralAverage(Student $student): ?float
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return null;
        }

        $avg = TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->avg('value');

        return $avg === null ? null : Grades::truncate2((float) $avg);
    }

    /**
     * Media generală la ACELAȘI semestru din anul precedent (treapta anterioară din foaia matricolă),
     * pentru comparația „semestru curent vs. același semestru anul trecut" (§2.3).
     */
    private function previousYearSameTermAverage(Student $student): ?float
    {
        // Treapta canonică (academic_year_id, ca Student::currentSchoolClass) — pe id, o înmatriculare
        // istorică completată retroactiv ar strica comparația „același semestru anul trecut" (#37).
        $currentLevel = $student->currentSchoolClass()?->grade_level;
        $currentTermNumber = Term::query()->where('is_current', true)->value('number');

        if ($currentLevel === null || $currentTermNumber === null) {
            return null;
        }

        $period = (int) $currentTermNumber === 1
            ? AcademicRecordPeriod::SemesterI
            : AcademicRecordPeriod::SemesterII;

        $avg = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('grade_level', $currentLevel - 1)
            ->where('period', $period)
            ->whereNotNull('value')
            ->avg('value');

        return $avg === null ? null : Grades::truncate2((float) $avg);
    }

    /**
     * Tendința pe ultimele două puncte ale unei serii.
     *
     * @param  list<float>  $values
     */
    private function trendOf(array $values): ?string
    {
        $count = count($values);

        if ($count < 2) {
            return null;
        }

        return $this->trendBetween($values[$count - 2], $values[$count - 1]);
    }

    private function trendBetween(float $previous, float $current): string
    {
        $delta = $current - $previous;

        if ($delta > self::TREND_THRESHOLD) {
            return 'up';
        }

        if ($delta < -self::TREND_THRESHOLD) {
            return 'down';
        }

        return 'stable';
    }
}
