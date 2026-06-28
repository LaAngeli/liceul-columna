<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarEventScope;
use App\Models\CalendarEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Evenimente MANUALE (modul Calendar v2). Cabinet: pentru fiecare copil, evenimentele vizibile clasei
 * lui (global ∪ treaptă ∪ clasă) — cele globale o singură dată (studentId null), restul legate de copil.
 * Staff: toate evenimentele (calendarul instituțional). Titlul în limba privitorului, fallback RO.
 */
class ManualEventProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($scope->isStaff) {
            $items = [];

            foreach ($this->rangeQuery($from, $to)->with('translations')->get() as $event) {
                array_push($items, ...$this->expand($event, $from, $to, null));
            }

            return $items;
        }

        $items = [];

        foreach ($scope->students as $student) {
            $class = $student->currentSchoolClass();

            $events = $this->rangeQuery($from, $to)
                ->visibleToClass($class)
                ->with('translations')
                ->get();

            foreach ($events as $event) {
                // Evenimentele globale nu sunt legate de un copil anume → studentId null (deduplicate
                // între frați); cele de treaptă/clasă rămân legate de copilul respectiv.
                $ownerId = $event->visibility_scope === CalendarEventScope::Global ? null : $student->id;

                array_push($items, ...$this->expand($event, $from, $to, $ownerId));
            }
        }

        return $items;
    }

    /**
     * Evenimentele care se suprapun cu intervalul [from, to].
     *
     * @return Builder<CalendarEvent>
     */
    private function rangeQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return CalendarEvent::query()
            ->where('starts_on', '<=', $toDate)
            ->where(function (Builder $query) use ($fromDate): void {
                $query->where('ends_on', '>=', $fromDate)
                    ->orWhere(function (Builder $inner) use ($fromDate): void {
                        $inner->whereNull('ends_on')->where('starts_on', '>=', $fromDate);
                    });
            });
    }

    /**
     * Un eveniment (eventual pe mai multe zile) → câte un {@see CalendarItem} pe fiecare zi din interval.
     *
     * @return list<CalendarItem>
     */
    private function expand(CalendarEvent $event, CarbonInterface $from, CarbonInterface $to, ?int $ownerId): array
    {
        $rangeStart = Carbon::parse($from)->startOfDay();
        $rangeEnd = Carbon::parse($to)->startOfDay();
        $start = Carbon::parse($event->starts_on)->startOfDay();
        $end = Carbon::parse($event->ends_on ?? $event->starts_on)->startOfDay();

        $cursor = $start->gt($rangeStart) ? $start : $rangeStart->copy();
        $limit = $end->lt($rangeEnd) ? $end : $rangeEnd;

        $title = $event->localizedTitle();
        $category = $event->type->category();
        $startTime = $event->start_time;
        $suffix = $ownerId !== null ? ":{$ownerId}" : '';

        $items = [];

        while ($cursor->lte($limit)) {
            $items[] = new CalendarItem(
                id: "calendar-event:{$event->id}:{$cursor->toDateString()}{$suffix}",
                source: 'calendar_event',
                category: $category,
                title: $title,
                date: $cursor->toDateString(),
                allDay: $startTime === null,
                startTime: $startTime,
                studentId: $ownerId,
                editable: true,
            );

            $cursor->addDay();
        }

        return $items;
    }
}
