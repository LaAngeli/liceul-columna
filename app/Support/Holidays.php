<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sursa unică a „zilei nelucrătoare" (weekend SAU sărbătoare/vacanță din tabelul holidays). Folosită
 * de {@see WorkingDays} pentru termenele motivărilor și de modulul Calendar (fundal + expandare orar).
 * Datele de sărbătoare sunt expandate din intervale și cache-uite; cache-ul e invalidat de Holiday.
 */
class Holidays
{
    private const CACHE_KEY = 'holidays.dates';

    public static function isNonWorkingDay(CarbonInterface $date): bool
    {
        return Carbon::parse($date)->isWeekend() || self::isHoliday($date);
    }

    public static function isHoliday(CarbonInterface $date): bool
    {
        return in_array(Carbon::parse($date)->toDateString(), self::dates(), true);
    }

    /**
     * Toate zilele de sărbătoare (Y-m-d), expandate din intervale. Cache-uit.
     *
     * @return list<string>
     */
    public static function dates(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, static function (): array {
            $dates = [];

            foreach (Holiday::all() as $holiday) {
                // Carbon::parse() = instanță MUTABILĂ; cast-urile de dată ale proiectului sunt
                // CarbonImmutable, pe care addDay() NU mutează (ar bucla la infinit).
                $cursor = Carbon::parse($holiday->starts_on)->startOfDay();
                $end = Carbon::parse($holiday->ends_on ?? $holiday->starts_on)->startOfDay();

                while ($cursor->lte($end)) {
                    $dates[] = $cursor->toDateString();
                    $cursor->addDay();
                }
            }

            return $dates;
        });
    }

    public static function flush(): null
    {
        Cache::forget(self::CACHE_KEY);

        return null;
    }
}
