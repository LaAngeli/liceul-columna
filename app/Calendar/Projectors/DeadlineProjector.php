<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Models\Absence;
use Carbon\CarbonInterface;

/**
 * Termene-limită vizibile familiei: termenul de depunere a motivării unei absențe (spec §2.1),
 * cât timp absența nu e încă motivată și nu a fost blocată. Eveniment deținut de elev.
 */
class DeadlineProjector implements CalendarProjector
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
            ->whereNotNull('motivation_deadline')
            ->where('is_motivated', false)
            ->whereNull('motivation_locked_at')
            ->whereBetween('motivation_deadline', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($absences as $absence) {
            if ($absence->motivation_deadline === null) {
                continue;
            }

            $items[] = new CalendarItem(
                id: "motivation-deadline:{$absence->id}",
                source: 'motivation_deadline',
                category: CalendarCategory::Deadline,
                title: (string) trans('cabinet_calendar.auto_motivation_deadline'),
                date: $absence->motivation_deadline->toDateString(),
                deepLink: "/cabinet/elev/{$absence->student_id}#motivations",
                studentId: $absence->student_id,
            );
        }

        return $items;
    }
}
