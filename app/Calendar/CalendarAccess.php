<?php

namespace App\Calendar;

use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Garda de acces a calendarului — sursa UNICĂ a deciziei „cine vede ce". Aplicată ÎNAINTE de orice
 * agregare (anti-IDOR): un viewer vede calendarul (PII) al unui elev doar dacă e familie (tutore
 * atribuit sau contul elevului) sau administrația/dirigintele îndreptățit. În plus, un eveniment
 * apare doar în zilele în care elevul era efectiv înrolat (transfer → fără scurgere între familii).
 */
class CalendarAccess
{
    /**
     * Poate `viewer` vedea calendarul acestui elev? (verificare la nivel de elev, nu de zi)
     */
    public function canViewStudentCalendar(User $viewer, Student $student): bool
    {
        if ($this->isFamilyOf($viewer, $student)) {
            return true;
        }

        // Administrația academică (super/director/prim-vicedir/AO) vede tot catalogul — CLAUDE.md §3.
        if ($viewer->isAdministrator()) {
            return true;
        }

        // Dirigintele clasei curente a elevului.
        $homeroom = $student->currentSchoolClass()?->homeroomTeacher;

        return $homeroom !== null
            && $viewer->teacher !== null
            && $homeroom->is($viewer->teacher);
    }

    /**
     * Familie = tutore atribuit (pivot guardian_student) SAU contul propriu al elevului.
     */
    public function isFamilyOf(User $viewer, Student $student): bool
    {
        return $student->user_id === $viewer->id
            || $viewer->students()->whereKey($student->id)->exists();
    }

    /**
     * Era elevul înrolat la data dată? Un eveniment dintr-o zi de dinainte de înrolare sau de după
     * plecare (transfer) NU trebuie să apară. Elev fără înrolări (date de test) → considerat vizibil.
     */
    public function wasEnrolledOn(Student $student, CarbonInterface $date): bool
    {
        $enrollments = $student->enrollments;

        if ($enrollments->isEmpty()) {
            return true;
        }

        $day = Carbon::parse($date)->startOfDay();

        return $enrollments->contains(function (object $enrollment) use ($day): bool {
            if ($enrollment->enrolled_on !== null && $day->lt($enrollment->enrolled_on)) {
                return false;
            }

            if ($enrollment->left_on !== null && $day->gt($enrollment->left_on)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Scope-ul calendarului pentru STAFF. MVP: calendarul INSTITUȚIONAL (structură — semestre/vacanțe
     * — + sesiuni de corigență publicate + viitoarele evenimente/ședințe manuale), fără agregare PII
     * per-elev la scară. Elevii rămân goi ⇒ doar evenimentele globale. Extinderea pe clase = v2.
     */
    public function staffScope(User $viewer): CalendarScope
    {
        /** @var Collection<int, Student> $empty */
        $empty = collect();

        return new CalendarScope($viewer, $empty, [], true);
    }

    /**
     * Elevii pe care `viewer` îi poate vedea în calendar (familie). Pentru staff broad rămâne gol —
     * calendarul de staff agregă pe clasele lui, nu pe elevi individuali.
     *
     * @return Collection<int, Student>
     */
    public function visibleStudents(User $viewer): Collection
    {
        // Tutore: prin pivotul guardian_student. Elev: fișa proprie, legată prin Student.user_id.
        // Înrolările sunt pre-încărcate pentru garda pe zi ({@see wasEnrolledOn}).
        $guarded = $viewer->students()->with('enrollments')->get();
        $own = Student::query()->where('user_id', $viewer->id)->with('enrollments')->get();

        return $guarded->concat($own)->unique('id')->values();
    }
}
