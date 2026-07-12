<?php

namespace App\Observers;

use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;

/**
 * Temele sunt legate de clasă prin PERECHEA text (grade_level, section) — moștenire legacy, fără
 * FK. Redenumirea literei (sau corectarea treptei) lăsa temele pe vechea combinație → elevii
 * clasei redenumite nu le mai vedeau în cabinet. Le purtăm după clasă, limitat la ferestrele
 * anului școlar al clasei (alte generații cu aceeași combinație nu sunt atinse).
 */
class SchoolClassObserver
{
    public function updated(SchoolClass $class): void
    {
        if (! $class->wasChanged(['section', 'grade_level'])) {
            return;
        }

        $year = $class->academicYear;

        if ($year === null || $year->starts_on === null || $year->ends_on === null) {
            return;
        }

        $oldSection = $class->getOriginal('section');

        // Temele cu section NULL sunt „pe toată treapta" — nu aparțin unei clase anume.
        if ($oldSection === null || trim((string) $oldSection) === '') {
            return;
        }

        HomeworkAssignment::query()
            ->where('grade_level', (int) $class->getOriginal('grade_level'))
            ->where('section', $oldSection)
            ->whereBetween('assigned_on', [$year->starts_on, $year->ends_on])
            ->update([
                'grade_level' => $class->grade_level,
                'section' => $class->section,
            ]);
    }
}
