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
 * de 50% practic imposibil de atins.
 *
 * Întoarce DOUĂ liste, fiindcă „nu e risc" și „nu pot calcula" nu sunt același lucru: `risks` =
 * disciplinele care depășesc pragul; `undetermined` = disciplinele la care elevul are absențe, dar
 * care lipsesc din orarul structurat, deci n-au numitor. Confundate într-o singură listă goală,
 * familia unui elev cu orar incomplet vedea exact aceeași pagină ca familia unui elev fără nicio
 * problemă — o tăcere care se citea drept confirmare.
 */
class ComputeDeferralRisk
{
    private const MAX_GRADES = 1;

    private const ABSENCE_THRESHOLD = 0.5;

    /**
     * @return array{risks: list<array{subject: string, absences: int, scheduled: int}>, undetermined: list<string>, noTimetable: bool}
     */
    public function for(Student $student): array
    {
        $class = $student->currentSchoolClass();
        $term = Term::query()->where('is_current', true)->first();

        if ($class === null || $term === null || $term->starts_on === null || $term->ends_on === null) {
            return ['risks' => [], 'undetermined' => [], 'noTimetable' => true];
        }

        $weeks = self::workingWeeks($term);

        $lessonsPerWeek = Lesson::query()
            ->where('school_class_id', $class->id)
            ->selectRaw('subject_id, count(*) as cnt')
            ->groupBy('subject_id')
            ->pluck('cnt', 'subject_id');

        // Disciplinele la care elevul CHIAR are absențe, dar care lipsesc din orarul structurat:
        // acolo numitorul nu există, deci riscul nu se poate calcula. Până acum astfel de discipline
        // dispăreau pur și simplu din rezultat, iar familia vedea aceeași pagină ca un elev fără
        // nicio problemă — „nu pot calcula" era indistinct de „nu e risc". Cauza pe date reale:
        // celulele de orar cu DOUĂ discipline pe grupe (nereprezentabile într-un slot) și cele două
        // fișe de engleză pe care orarele nu le disting. Vezi NOTE-DEV-DEPLOY §1.7.
        $undetermined = $this->subjectsWithoutSchedule($student, $term, array_values($lessonsPerWeek->keys()->all()));

        // Clasa fără NICIUN slot: nu enumerăm toate disciplinele ei (ar fi un zid de text care
        // sugerează 12 probleme), ci spunem o singură dată că orarul lipsește cu totul.
        if ($lessonsPerWeek->isEmpty()) {
            return ['risks' => [], 'undetermined' => [], 'noTimetable' => true];
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

        return ['risks' => $risks, 'undetermined' => $undetermined, 'noTimetable' => false];
    }

    /**
     * Disciplinele la care elevul are absențe în semestru, dar care NU apar în orarul structurat al
     * clasei — deci fără număr de lecții programate, deci fără prag de comparat.
     *
     * @param  list<int|string>  $scheduledSubjectIds
     * @return list<string>
     */
    private function subjectsWithoutSchedule(Student $student, Term $term, array $scheduledSubjectIds): array
    {
        $missing = $student->absences()
            ->where('term_id', $term->id)
            ->whereNotNull('subject_id')
            ->whereNotIn('subject_id', $scheduledSubjectIds)
            ->distinct()
            ->pluck('subject_id');

        if ($missing->isEmpty()) {
            return [];
        }

        $names = Subject::query()
            ->whereIn('id', $missing)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): string => ContentTranslator::subject($name))
            ->values();

        return array_values($names->all());
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
