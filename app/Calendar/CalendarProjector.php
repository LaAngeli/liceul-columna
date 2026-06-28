<?php

namespace App\Calendar;

use Carbon\CarbonInterface;

/**
 * Un proiector traduce o sursă datată (teme, absențe, orar, termene…) în {@see CalendarItem}-uri
 * pentru un interval și un context dat. Proiectează LA CITIRE — nu stochează evenimente derivate.
 */
interface CalendarProjector
{
    /**
     * Evenimentele acestui proiector pentru intervalul [from, to], în contextul scope.
     *
     * @return list<CalendarItem>
     */
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array;
}
