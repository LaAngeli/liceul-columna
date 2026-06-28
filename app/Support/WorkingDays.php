<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcul de zile LUCRĂTOARE pentru termenele de motivare (spec §2.1).
 *
 * Exclude weekendurile ȘI sărbătorile/vacanțele din tabelul `holidays` (sursa unică {@see Holidays},
 * întreținută de administratorul operațional). Tabel gol = comportament identic cu „doar weekenduri".
 */
class WorkingDays
{
    /**
     * Adaugă `$days` zile lucrătoare la o dată, sărind weekendurile și zilele nelucrătoare.
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

            if (! Holidays::isNonWorkingDay($date)) {
                $added++;
            }
        }

        return $date;
    }
}
