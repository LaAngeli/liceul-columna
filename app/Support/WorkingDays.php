<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcul de zile LUCRĂTOARE pentru termenele de motivare (spec §2.1).
 *
 * v1: exclude doar weekendurile (sâmbătă/duminică). Sărbătorile legale (RM) se pot adăuga ulterior
 * printr-un tabel întreținut de administratorul operațional, FĂRĂ a schimba apelanții — vezi roadmap.
 */
class WorkingDays
{
    /**
     * Adaugă `$days` zile lucrătoare la o dată, sărind weekendurile.
     *
     * Acceptă orice implementare Carbon (mutable sau immutable — proiectul folosește CarbonImmutable
     * la cast-uri) și lucrează pe o copie MUTABILĂ internă.
     */
    public static function add(CarbonInterface $from, int $days): Carbon
    {
        $date = Carbon::parse($from)->startOfDay();
        $added = 0;

        while ($added < $days) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $added++;
            }
        }

        return $date;
    }
}
