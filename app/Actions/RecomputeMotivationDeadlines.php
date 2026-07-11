<?php

namespace App\Actions;

use App\Models\Absence;
use App\Support\WorkingDays;

/**
 * Recalculează termenele de motivare DESCHISE după o schimbare a zilelor libere (spec §2.1):
 * termenul e un SNAPSHOT („data absenței + 5 zile lucrătoare") calculat la creare — dacă AO
 * adaugă/mută o vacanță DUPĂ, termenele deja stocate ar rămâne calculate pe vechiul calendar,
 * iar familia ar pierde (sau câștiga) zile fără nicio bază. Se recalculează DOAR cererile încă
 * judecabile: nemotivate și neconsolidate (motivation_locked_at null); termenele deja închise
 * de consolidarea zilnică rămân istorice.
 */
class RecomputeMotivationDeadlines
{
    public function run(): int
    {
        $updated = 0;

        Absence::query()
            ->where('is_motivated', false)
            ->whereNull('motivation_locked_at')
            ->whereNotNull('motivation_deadline')
            ->each(function (Absence $absence) use (&$updated): void {
                $fresh = WorkingDays::add($absence->occurred_on, 5);

                if (! $fresh->isSameDay($absence->motivation_deadline)) {
                    // saveQuietly: recalculul e mecanic (nu o „modificare" de consemnat separat)
                    // și nu trebuie să re-declanșeze observerii absenței.
                    $absence->forceFill(['motivation_deadline' => $fresh])->saveQuietly();
                    $updated++;
                }
            });

        return $updated;
    }
}
