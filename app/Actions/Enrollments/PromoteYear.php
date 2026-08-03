<?php

namespace App\Actions\Enrollments;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Support\SchoolCalendar;

/**
 * Promovarea unui AN ÎNTREG: fiecare clasă cu elevi activi din anul-sursă își trimite elevii în
 * clasa corespondentă a anului-țintă (treaptă+1, aceeași secțiune).
 *
 * Exista deja, dar trăia în pagina Înmatriculări. A fost scoasă aici când a apărut a doua
 * suprafață care avea nevoie de ea — ecranul unic de deschidere a anului: două copii ale unei
 * operațiuni care mută sute de elevi ar fi divergent la prima corectură.
 *
 * Clasele FĂRĂ corespondent rămân cu ținta null și se RAPORTEAZĂ: acolo lipsesc clase de creat,
 * iar o promovare tăcută le-ar pierde elevii. Restul regulilor stau în {@see PromoteClass}
 * (doar activii, an închis refuzat, rândurile existente sărite — deci se poate relua).
 */
class PromoteYear
{
    public function __construct(private readonly PromoteClass $promote) {}

    /**
     * Perechile clasă-sursă → clasă-țintă, cu numărul de elevi activi ai fiecăreia.
     *
     * @return array<int, array{source: SchoolClass, target: SchoolClass|null, students: int}>
     */
    public function plan(?int $sourceYearId, ?int $targetYearId): array
    {
        if ($sourceYearId === null || $targetYearId === null || $sourceYearId === $targetYearId) {
            return [];
        }

        $counts = Enrollment::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->whereNull('left_on')
            ->where('academic_year_id', $sourceYearId)
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->groupBy('school_class_id')
            ->pluck('aggregate', 'school_class_id');

        return SchoolClass::query()
            ->whereKey($counts->keys()->all())
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class): array => [
                'source' => $class,
                'target' => $this->promote->suggestTarget($class, $targetYearId),
                'students' => (int) ($counts->get($class->id) ?? 0),
            ])
            ->all();
    }

    /**
     * Execută promovarea pentru toate perechile mapate.
     *
     * @return array{enrolled: int, skipped: int, classes: int, unmapped: int}
     */
    public function handle(?int $sourceYearId, ?int $targetYearId): array
    {
        $plan = $this->plan($sourceYearId, $targetYearId);

        $mapped = array_values(array_filter($plan, fn (array $row): bool => $row['target'] !== null));

        $enrolled = 0;
        $skipped = 0;

        foreach ($mapped as $row) {
            $result = $this->promote->handle($row['source'], $row['target'], SchoolCalendar::localNow());
            $enrolled += $result['enrolled'];
            $skipped += $result['skipped'];
        }

        return [
            'enrolled' => $enrolled,
            'skipped' => $skipped,
            'classes' => count($mapped),
            'unmapped' => count($plan) - count($mapped),
        ];
    }
}
