<?php

namespace App\Calendar;

use Carbon\CarbonInterface;

/**
 * Agregă evenimentele din toate sursele (proiectoare înregistrate) pentru un interval + context, le
 * deduplică și le sortează. Proiectoarele proiectează LA CITIRE din modulul lor; aici doar le unim.
 * Dedup-ul pe (proprietar, zi, categorie, titlu) protejează contra surselor duplicate (ex. orar vs
 * ședință declarate în două locuri).
 */
class CalendarAggregator
{
    /** @var list<CalendarProjector> */
    private array $projectors;

    /**
     * @param  iterable<CalendarProjector>  $projectors
     */
    public function __construct(iterable $projectors = [])
    {
        $this->projectors = is_array($projectors)
            ? array_values($projectors)
            : iterator_to_array($projectors, false);
    }

    /**
     * @return list<CalendarItem>
     */
    public function collect(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $items = [];

        foreach ($this->projectors as $projector) {
            foreach ($projector->project($scope, $from, $to) as $item) {
                $items[] = $item;
            }
        }

        return $this->dedupeAndSort($items);
    }

    /**
     * @param  list<CalendarItem>  $items
     * @return list<CalendarItem>
     */
    private function dedupeAndSort(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            // id-ul codifică identitatea logică a ocurenței (sursă + zi + proprietar): evenimentele
            // globale emise per-frate au același id → se deduplică; evenimentele distincte cu același
            // titlu/zi/categorie NU se mai pierd (vezi review v2).
            $unique[$item->id] ??= $item;
        }

        $result = array_values($unique);

        usort($result, static fn (CalendarItem $a, CalendarItem $b): int => [$a->date, $a->startTime ?? '', $a->category->value]
            <=> [$b->date, $b->startTime ?? '', $b->category->value]);

        return $result;
    }
}
