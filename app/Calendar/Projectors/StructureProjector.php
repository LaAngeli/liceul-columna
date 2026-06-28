<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Models\Holiday;
use App\Models\Term;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Structura anului — informație GLOBALĂ (non-PII): limitele de semestru și vacanțele/zilele libere.
 * Vacanțele se proiectează ca fundal pe FIECARE zi din interval, ca frontend-ul să poată hașura banda.
 */
class StructureProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $rangeStart = Carbon::parse($from)->startOfDay();
        $rangeEnd = Carbon::parse($to)->startOfDay();

        $items = [];

        foreach (Term::all() as $term) {
            if ($term->starts_on !== null && $this->inRange($term->starts_on, $rangeStart, $rangeEnd)) {
                $items[] = new CalendarItem(
                    id: "term-start:{$term->id}",
                    source: 'term',
                    category: CalendarCategory::Structure,
                    title: (string) trans('cabinet_calendar.auto_term_start'),
                    date: $term->starts_on->toDateString(),
                    meta: ['boundary' => 'start'],
                );
            }

            if ($term->ends_on !== null && $this->inRange($term->ends_on, $rangeStart, $rangeEnd)) {
                $items[] = new CalendarItem(
                    id: "term-end:{$term->id}",
                    source: 'term',
                    category: CalendarCategory::Structure,
                    title: (string) trans('cabinet_calendar.auto_term_end'),
                    date: $term->ends_on->toDateString(),
                    meta: ['boundary' => 'end'],
                );
            }
        }

        foreach (Holiday::all() as $holiday) {
            $start = Carbon::parse($holiday->starts_on)->startOfDay();
            $end = Carbon::parse($holiday->ends_on ?? $holiday->starts_on)->startOfDay();

            $cursor = $start->gt($rangeStart) ? $start : $rangeStart->copy();
            $limit = $end->lt($rangeEnd) ? $end : $rangeEnd;

            while ($cursor->lte($limit)) {
                $items[] = new CalendarItem(
                    id: "holiday:{$holiday->id}:{$cursor->toDateString()}",
                    source: 'holiday',
                    category: CalendarCategory::Structure,
                    title: $holiday->name,
                    date: $cursor->toDateString(),
                    meta: ['kind' => 'holiday'],
                );

                $cursor->addDay();
            }
        }

        return $items;
    }

    private function inRange(CarbonInterface $date, Carbon $rangeStart, Carbon $rangeEnd): bool
    {
        $day = Carbon::parse($date)->startOfDay();

        return $day->gte($rangeStart) && $day->lte($rangeEnd);
    }
}
