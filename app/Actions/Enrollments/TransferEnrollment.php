<?php

namespace App\Actions\Enrollments;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * TRANSFERUL între clasele ACELUIAȘI an — mutarea reală de registru, scoasă din tabel într-o
 * Action ca să poată fi folosită și pe o selecție întreagă (restructurare 2026-08-02).
 *
 * Notele și absențele deja consemnate rămân pe clasa VECHE (`Grade.school_class_id` e snapshotul
 * de la consemnare — corect istoric); catalogul de mai departe curge pe clasa nouă. Transferul
 * între ANI nu există aici: acela e {@see PromoteClass}.
 */
class TransferEnrollment
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array{moved: int, skipped: int}
     */
    public function handle(array $enrollmentIds, SchoolClass $target): array
    {
        $enrollments = Enrollment::query()
            ->whereKey($enrollmentIds)
            // Cine a plecat nu se mai transferă — rândul lui e închis.
            ->whereNull('left_on')
            ->get();

        $movable = $enrollments->filter(
            fn (Enrollment $enrollment): bool => (int) $enrollment->academic_year_id === (int) $target->academic_year_id
                && (int) $enrollment->school_class_id !== (int) $target->getKey(),
        );

        if ($movable->isNotEmpty()) {
            DB::transaction(function () use ($movable, $target): void {
                foreach ($movable as $enrollment) {
                    // Prin model: Enrollment e Auditable, iar vechi→nou trebuie să rămână în jurnal.
                    $enrollment->update(['school_class_id' => $target->getKey()]);
                }
            });
        }

        return [
            'moved' => $movable->count(),
            'skipped' => count(array_unique($enrollmentIds)) - $movable->count(),
        ];
    }
}
