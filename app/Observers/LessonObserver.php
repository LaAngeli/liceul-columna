<?php

namespace App\Observers;

use App\Models\Lesson;
use App\Models\SchoolClass;

/**
 * Anul unei lecții NU e o alegere: clasa aparține deja unui an școlar, iar slotul îl moștenește.
 *
 * Câmpul `academic_year_id` există pe `lessons` ca dimensiune de interogare (riscul de amânare
 * numără lecțiile programate pe an), dar a-l lăsa editabil separat de clasă înseamnă că un slot
 * poate ajunge într-un an în care clasa lui nici nu exista — lecția dispare atunci din toate
 * calculele, fără niciun semnal. Invariantul se aplică aici, nu în formular: formularul e o cale de
 * scriere între mai multe (import, seed, API), iar o regulă care ține doar în UI nu e o regulă.
 */
class LessonObserver
{
    public function saving(Lesson $lesson): void
    {
        // `getAttribute`, nu proprietatea tipizată: pe un model nesalvat cheile pot lipsi de tot,
        // iar tipul derivat din schemă (coloană NOT NULL) n-ar lăsa comparația cu null.
        $classId = $lesson->getAttribute('school_class_id');

        if ($classId === null) {
            return;
        }

        // Doar când clasa e (re)atribuită sau anul lipsește — altfel am interoga la fiecare salvare.
        if (! $lesson->isDirty('school_class_id') && $lesson->getAttribute('academic_year_id') !== null) {
            return;
        }

        $yearId = SchoolClass::query()
            ->whereKey($classId)
            ->value('academic_year_id');

        if ($yearId !== null) {
            $lesson->setAttribute('academic_year_id', (int) $yearId);
        }
    }
}
