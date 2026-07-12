<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Support\ContentTranslator;
use App\Support\Holidays;
use Illuminate\Support\Carbon;

/**
 * Riscul de AMÂNARE pe discipline (spec §2.1): un elev riscă amânarea la o disciplină dacă are prea
 * puține note (≤ 1) ȘI a lipsit la peste 50% din lecțiile programate. Numărul de lecții programate =
 * lecții/săptămână (din orarul structurat) × săptămânile LUCRĂTOARE ale semestrului curent —
 * zilele din `holidays` nu se numără (#31): cu vacanțele incluse, numitorul era umflat și pragul
 * de 50% practic imposibil de atins. Fără orar sau fără semestru curent cu date, nu se calculează
 * (listă goală).
 */
class ComputeDeferralRisk
{
    private const MAX_GRADES = 1;

    private const ABSENCE_THRESHOLD = 0.5;

    /**
     * @return list<array{subject: string, absences: int, scheduled: int}>
     */
    public function for(Student $student): array
    {
        $class = $student->currentSchoolClass();
        $term = Term::query()->where('is_current', true)->first();

        if ($class === null || $term === null || $term->starts_on === null || $term->ends_on === null) {
            return [];
        }

        $weeks = self::workingWeeks($term);

        $lessonsPerWeek = Lesson::query()
            ->where('school_class_id', $class->id)
            ->selectRaw('subject_id, count(*) as cnt')
            ->groupBy('subject_id')
            ->pluck('cnt', 'subject_id');

        if ($lessonsPerWeek->isEmpty()) {
            return [];
        }

        $subjectNames = Subject::query()->whereIn('id', $lessonsPerWeek->keys())->pluck('name', 'id');

        $risks = [];
        foreach ($lessonsPerWeek as $subjectId => $count) {
            $scheduled = (int) $count * $weeks;

            if ($scheduled === 0) {
                continue;
            }

            $absences = $student->absences()
                ->where('subject_id', $subjectId)
                ->where('term_id', $term->id)
                ->count();

            $grades = $student->grades()
                ->where('subject_id', $subjectId)
                ->where('term_id', $term->id)
                ->whereNull('annulled_at')
                ->count();

            if ($grades <= self::MAX_GRADES && $absences > self::ABSENCE_THRESHOLD * $scheduled) {
                $name = $subjectNames[$subjectId] ?? (string) $subjectId;
                $risks[] = [
                    'subject' => ContentTranslator::subject((string) $name),
                    'absences' => $absences,
                    'scheduled' => $scheduled,
                ];
            }
        }

        return $risks;
    }

    /**
     * Săptămânile de ȘCOALĂ ale semestrului: zilele lucrătoare (fără weekenduri și fără zilele din
     * `holidays`) împărțite la 5. Minim 1 — un semestru abia început nu trebuie să dea împărțire
     * la zero.
     */
    private static function workingWeeks(Term $term): int
    {
        $workingDays = 0;
        $cursor = Carbon::parse($term->starts_on)->startOfDay();
        $end = Carbon::parse($term->ends_on)->startOfDay();

        while ($cursor->lte($end)) {
            if (! Holidays::isNonWorkingDay($cursor)) {
                $workingDays++;
            }

            $cursor = $cursor->addDay();
        }

        return max(1, (int) ceil($workingDays / 5));
    }
}
