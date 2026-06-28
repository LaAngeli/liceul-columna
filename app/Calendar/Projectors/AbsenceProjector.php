<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Models\Absence;
use Carbon\CarbonInterface;

/**
 * Absențe (spec §2.1). Eveniment deținut de elev (student_id) — fără risc de scurgere între familii,
 * deci nu mai e nevoie de garda pe zi.
 */
class AbsenceProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $studentIds = $scope->studentIds();

        if ($studentIds === []) {
            return [];
        }

        $items = [];

        $absences = Absence::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($absences as $absence) {
            $title = (string) trans('cabinet_calendar.'.($absence->is_motivated ? 'auto_absence_motivated' : 'auto_absence'));

            $items[] = new CalendarItem(
                id: "absence:{$absence->id}",
                source: 'absence',
                category: CalendarCategory::Absence,
                title: $title,
                date: $absence->occurred_on->toDateString(),
                deepLink: "/cabinet/elev/{$absence->student_id}#absences",
                studentId: $absence->student_id,
                meta: ['motivated' => $absence->is_motivated],
            );
        }

        return $items;
    }
}
