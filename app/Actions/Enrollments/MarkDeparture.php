<?php

namespace App\Actions\Enrollments;

use App\Enums\DepartureReason;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PLECAREA din școală: `left_on`, nu ștergere — rândul de registru rămâne, cu istoricul lui
 * (restructurare 2026-08-02: scoasă din tabel ca să servească și o selecție întreagă, de pildă
 * o clasă care se desființează la mijloc de an).
 *
 * Data plecării nu poate PRECEDA înmatricularea (interval negativ), dar poate fi CHIAR ZIUA ei:
 * un elev înscris și retras în aceeași zi e o zi de registru validă — regula veche cerea strict
 * „după", iar modalul propunea `enrolled_on + 1 zi`, deci cazul real era imposibil de consemnat.
 */
class MarkDeparture
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array{marked: int, skipped: int}
     */
    public function handle(array $enrollmentIds, Carbon $leftOn, ?DepartureReason $reason = null): array
    {
        $date = $leftOn->copy()->startOfDay();

        $enrollments = Enrollment::query()
            ->whereKey($enrollmentIds)
            ->whereNull('left_on')
            ->get();

        $markable = $enrollments->filter(
            fn (Enrollment $enrollment): bool => $enrollment->enrolled_on === null
                || $enrollment->enrolled_on->lessThanOrEqualTo($date),
        );

        if ($markable->isNotEmpty()) {
            DB::transaction(function () use ($markable, $date, $reason): void {
                foreach ($markable as $enrollment) {
                    $enrollment->update(['left_on' => $date, 'departure_reason' => $reason]);
                }
            });
        }

        return [
            'marked' => $markable->count(),
            'skipped' => count(array_unique($enrollmentIds)) - $markable->count(),
        ];
    }
}
