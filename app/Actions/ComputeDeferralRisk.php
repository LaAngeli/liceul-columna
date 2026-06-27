<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Support\ContentTranslator;

/**
 * Riscul de AMÂNARE pe discipline (spec §2.1): un elev riscă amânarea la o disciplină dacă are prea
 * puține note (≤ 1) ȘI a lipsit la peste 50% din lecțiile programate. Numărul de lecții programate =
 * lecții/săptămână (din orarul structurat) × săptămânile semestrului curent. Fără orar sau fără
 * semestru curent cu date, nu se calculează (listă goală).
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

        $weeks = max(1, (int) ceil($term->starts_on->diffInDays($term->ends_on) / 7));

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
}
