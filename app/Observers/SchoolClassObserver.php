<?php

namespace App\Observers;

use App\Actions\SyncHomeroomRole;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Teacher;

/**
 * Două responsabilități, ambele legate de „clasa s-a schimbat, altceva trebuie să țină pasul":
 *
 * 1. Temele sunt legate de clasă prin PERECHEA text (grade_level, section) — moștenire legacy,
 *    fără FK. Redenumirea literei (sau corectarea treptei) lăsa temele pe vechea combinație →
 *    elevii clasei redenumite nu le mai vedeau în cabinet. Le purtăm după clasă, limitat la
 *    ferestrele anului școlar al clasei (alte generații cu aceeași combinație nu sunt atinse).
 *
 * 2. Rolul „Diriginte" e DERIVAT din desemnare ({@see SyncHomeroomRole}): orice atingere a lui
 *    `homeroom_teacher_id` — inclusiv ștergerea sau restaurarea clasei — readuce eticheta
 *    conturilor afectate în acord cu realitatea, la ambele capete ale mutării.
 */
class SchoolClassObserver
{
    public function __construct(private SyncHomeroomRole $syncHomeroomRole) {}

    public function created(SchoolClass $class): void
    {
        $this->syncHomeroom($class->homeroom_teacher_id);
    }

    /** Clasa ștearsă nu mai contează la dirigenție (relația respectă soft delete). */
    public function deleted(SchoolClass $class): void
    {
        $this->syncHomeroom($class->homeroom_teacher_id);
    }

    public function restored(SchoolClass $class): void
    {
        $this->syncHomeroom($class->homeroom_teacher_id);
    }

    public function updated(SchoolClass $class): void
    {
        if ($class->wasChanged('homeroom_teacher_id')) {
            // AMBELE capete: cel care a pierdut clasa poate rămâne fără nicio dirigenție, iar
            // cel care a primit-o trebuie să-și capete eticheta.
            $this->syncHomeroom($class->getOriginal('homeroom_teacher_id'));
            $this->syncHomeroom($class->homeroom_teacher_id);
        }

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

    private function syncHomeroom(mixed $teacherId): void
    {
        if (! is_int($teacherId) && ! is_string($teacherId)) {
            return;
        }

        $this->syncHomeroomRole->forTeacher(Teacher::query()->find((int) $teacherId));
    }
}
