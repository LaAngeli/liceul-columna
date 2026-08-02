<?php

namespace App\Actions\Enrollments;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolCalendar;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Înmatricularea MAI MULTOR elevi într-o clasă, dintr-o singură operațiune (restructurare
 * 2026-08-02). Până acum registrul cunoștea o singură cale: formularul cu un elev — la deschiderea
 * unui an școlar asta însemna 773 de treceri prin formular, adică exact munca pe care secțiunea
 * trebuia să o facă posibilă.
 *
 * Reguli, aplicate pe SERVER (interfața doar le oglindește):
 *   • clasa dă anul — nu se poate înmatricula „în anul X la o clasă a anului Y";
 *   • anul ÎNCHIS nu primește înmatriculări noi (registrul lui e istorie, nu spațiu de lucru);
 *   • un elev = o singură înmatriculare pe an: cei care au deja una (chiar ARHIVATĂ, fiindcă
 *     indexul unic o vede) se SAR și se raportează, nu opresc restul operațiunii;
 *   • elevii inexistenți sau arhivați se ignoră tăcut — vin dintr-o listă, nu din tastatură.
 *
 * Totul într-o tranzacție: o cădere la mijloc nu lasă jumătate de clasă înmatriculată.
 */
class EnrollStudents
{
    /**
     * @param  list<int>  $studentIds
     * @return array{enrolled: int, skipped: int, blocked: bool}
     */
    public function handle(SchoolClass $class, array $studentIds, ?Carbon $enrolledOn = null): array
    {
        $year = $class->academicYear;

        if ($year === null || $year->closed_at !== null) {
            return ['enrolled' => 0, 'skipped' => count($studentIds), 'blocked' => true];
        }

        $date = ($enrolledOn ?? SchoolCalendar::localNow())->startOfDay();

        // Elevii ELIGIBILI: există, nu sunt arhivați și n-au nicio înmatriculare în anul clasei.
        // Verificarea se face o dată, în bloc — nu un SELECT per elev.
        $eligible = Student::query()
            ->whereKey(array_values(array_unique(array_map(intval(...), $studentIds))))
            ->whereDoesntHave('enrollments', fn ($query) => $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('academic_year_id', $class->academic_year_id))
            ->pluck('id')
            ->all();

        if ($eligible === []) {
            return ['enrolled' => 0, 'skipped' => count($studentIds), 'blocked' => false];
        }

        DB::transaction(function () use ($eligible, $class, $date): void {
            foreach ($eligible as $studentId) {
                // Prin MODEL, nu prin query builder: înmatricularea e Auditable (L133) — inserția
                // în masă ar sări peste jurnal exact la operațiunea cu cel mai mare efect.
                Enrollment::query()->create([
                    'student_id' => $studentId,
                    'school_class_id' => $class->getKey(),
                    'academic_year_id' => $class->academic_year_id,
                    'enrolled_on' => $date,
                ]);
            }
        });

        return [
            'enrolled' => count($eligible),
            'skipped' => count(array_unique($studentIds)) - count($eligible),
            'blocked' => false,
        ];
    }
}
