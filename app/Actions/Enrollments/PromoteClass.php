<?php

namespace App\Actions\Enrollments;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Carbon;

/**
 * PROMOVAREA unei clase în anul următor: elevii ACTIVI ai clasei-sursă devin înmatriculările
 * clasei-țintă din alt an școlar (restructurare 2026-08-02).
 *
 * Era operațiunea care lipsea cu totul din registru: „transferul între ani nu există — acela e
 * promovarea", scria în cod, dar promovarea nu exista nicăieri. Fără ea, deschiderea unui an
 * școlar însemna reintroducerea manuală a întregii școli.
 *
 * Ce NU se promovează, deliberat:
 *   • elevii cu `left_on` — au plecat din școală în cursul anului sursă;
 *   • elevii deja înmatriculați în anul-țintă (mutări făcute manual înainte) — se sar, nu se dublează.
 * Înmatriculările vechi rămân NEATINSE: anul sursă e registrul lui, cu istoricul lui.
 */
class PromoteClass
{
    public function __construct(private readonly EnrollStudents $enroll) {}

    /**
     * @return array{enrolled: int, skipped: int, blocked: bool}
     */
    public function handle(SchoolClass $source, SchoolClass $target, ?Carbon $enrolledOn = null): array
    {
        // Promovarea traversează ANI. Aceeași pereche de ani = transfer între clase, alt proces
        // (fără mutarea registrului), iar o țintă în anul sursă ar dubla tăcut rândul anului.
        if ((int) $source->academic_year_id === (int) $target->academic_year_id) {
            return ['enrolled' => 0, 'skipped' => 0, 'blocked' => true];
        }

        $studentIds = array_values(Enrollment::query()
            ->where('school_class_id', $source->getKey())
            ->whereNull('left_on')
            ->pluck('student_id')
            ->map(intval(...))
            ->all());

        if ($studentIds === []) {
            return ['enrolled' => 0, 'skipped' => 0, 'blocked' => false];
        }

        return $this->enroll->handle($target, $studentIds, $enrolledOn);
    }

    /**
     * Ținta SUGERATĂ pentru o clasă: aceeași literă/secțiune, o treaptă mai sus, în anul ales —
     * tiparul real al promovării (I A → II A). Fără potrivire exactă, nicio sugestie: registrul
     * nu ghicește unde merge o clasă.
     */
    public function suggestTarget(SchoolClass $source, int $targetYearId): ?SchoolClass
    {
        return SchoolClass::query()
            ->where('academic_year_id', $targetYearId)
            ->where('grade_level', $source->grade_level + 1)
            ->when(
                $source->section !== null,
                fn ($query) => $query->where('section', $source->section),
                fn ($query) => $query->whereNull('section'),
            )
            ->orderBy('name')
            ->first();
    }
}
