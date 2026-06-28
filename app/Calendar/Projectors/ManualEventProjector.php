<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarEventScope;
use App\Models\CalendarEvent;
use App\Models\Student;
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
    public function __construct(private readonly CalendarAccess $access) {}

    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($scope->isStaff) {
            $items = [];

            foreach ($this->rangeQuery($from, $to)->with('translations')->get() as $event) {
                array_push($items, ...$this->expand($event, $from, $to, null, null));
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
                $isGlobal = $event->visibility_scope === CalendarEventScope::Global;

                // Evenimentele globale nu sunt legate de un copil anume → studentId null (deduplicate
                // între frați); cele de treaptă/clasă rămân legate de copil ȘI doar pe zilele în care
                // era înrolat (transfer → fără scurgere între familii, ca la teme).
                array_push($items, ...$this->expand(
                    $event,
                    $from,
                    $to,
                    $isGlobal ? null : $student->id,
                    $isGlobal ? null : $student,
                ));
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
     * Dacă `$enrolled` e dat (eveniment de treaptă/clasă), se emit doar zilele de înrolare ale elevului.
     *
     * @return list<CalendarItem>
     */
    private function expand(CalendarEvent $event, CarbonInterface $from, CarbonInterface $to, ?int $ownerId, ?Student $enrolled): array
    {
        $rangeStart = Carbon::parse($from)->startOfDay();
        $rangeEnd = Carbon::parse($to)->startOfDay();
        $start = Carbon::parse($event->starts_on)->startOfDay();
        $end = Carbon::parse($event->ends_on ?? $event->starts_on)->startOfDay();

        // Interval inversat (ends_on < starts_on) — posibil doar prin scrieri directe (formularul îl
        // previne cu afterOrEqual). Degradăm controlat, fără a produce evenimente fantomă.
        if ($end->lt($start)) {
            return [];
        }

        $cursor = $start->gt($rangeStart) ? $start : $rangeStart->copy();
        $limit = $end->lt($rangeEnd) ? $end : $rangeEnd;

        $title = $event->localizedTitle();
        $category = $event->type->category();
        $startTime = $event->start_time;
        $suffix = $ownerId !== null ? ":{$ownerId}" : '';

        $items = [];

        while ($cursor->lte($limit)) {
            if ($enrolled !== null && ! $this->access->wasEnrolledOn($enrolled, $cursor)) {
                $cursor->addDay();

                continue;
            }

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
