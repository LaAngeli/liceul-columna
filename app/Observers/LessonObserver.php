<?php

namespace App\Observers;

use App\Actions\PublishClassTimetable;
use App\Models\Lesson;
use App\Models\SchoolClass;

/**
 * Două invariante ale slotului de orar: anul îl moștenește de la clasă, iar orarul publicat al
 * clasei se recompune la orice schimbare ({@see PublishClassTimetable}).
 *
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
        // Etichetele de afișare (`title`, `teacher_name`) există ca să păstreze forma în care ora e
        // scrisă în orarele existente. Când cineva schimbă din panou disciplina sau profesorul,
        // eticheta veche ar continua să se afișeze peste alegerea nouă — deci cade. Doar dacă nu a
        // fost dată chiar acum: importul scrie ambele în aceeași operație, iar acela e cazul în care
        // eticheta TREBUIE păstrată.
        if ($lesson->exists && $lesson->isDirty('subject_id') && ! $lesson->isDirty('title')) {
            $lesson->setAttribute('title', null);
        }

        if ($lesson->exists && $lesson->isDirty('teacher_id') && ! $lesson->isDirty('teacher_name')) {
            $lesson->setAttribute('teacher_name', null);
        }

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

    public function saved(Lesson $lesson): void
    {
        $this->republish($lesson);
    }

    public function deleted(Lesson $lesson): void
    {
        $this->republish($lesson);
    }

    /**
     * Orarul publicat al clasei se recompune la fiecare schimbare de slot: sloturile sunt SURSA, iar
     * tabelul de pe site derivatul. Publicarea la salvare e ce face imposibilă divergența dintre
     * ele — un „republică" manual ar fi doar o invitație la uitat.
     *
     * Clasa se ia din baza de date, nu din relația încărcată: la ștergere modelul poate veni fără ea.
     */
    private function republish(Lesson $lesson): void
    {
        if (PublishClassTimetable::isSuspended()) {
            return;
        }

        $class = SchoolClass::query()
            ->whereKey($lesson->getAttribute('school_class_id'))
            ->first();

        if ($class !== null) {
            app(PublishClassTimetable::class)->handle($class);
        }
    }
}
