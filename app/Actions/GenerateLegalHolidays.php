<?php

namespace App\Actions;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Sărbătorile legale nelucrătoare ale Republicii Moldova (Codul muncii, art. 111) pentru un
 * interval dat — de regulă anul școlar selectat în planificator. Datele fixe sunt din lege;
 * Paștele ortodox se CALCULEAZĂ (computus iulian Meeus + decalajul gregorian de 13 zile,
 * valabil 1900–2099), iar Paștele Blajinilor = luni, la 8 zile după duminica Paștelui.
 *
 * Lista e o PROPUNERE pe care administratorul o confirmă bifă cu bifă în planificator (legea se
 * mai schimbă; hramul e al Chișinăului) — acțiunea nu creează nimic nebifat și nu dublează
 * înregistrările existente (aceeași denumire + aceeași dată de început).
 */
class GenerateLegalHolidays
{
    /**
     * Candidații din interval, ordonați cronologic.
     *
     * @return list<array{name: string, starts_on: string, ends_on: string|null}>
     */
    public function candidatesBetween(Carbon $from, Carbon $to): array
    {
        $candidates = [];

        foreach (range($from->year, $to->year) as $year) {
            $easter = $this->orthodoxEaster($year);

            $yearly = [
                ['name' => __('panel.holiday_planner.legal.new_year'), 'month' => 1, 'day' => 1, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.christmas_old'), 'month' => 1, 'day' => 7, 'length' => 2],
                ['name' => __('panel.holiday_planner.legal.womens_day'), 'month' => 3, 'day' => 8, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.easter'), 'date' => $easter, 'length' => 2],
                ['name' => __('panel.holiday_planner.legal.memorial_easter'), 'date' => $easter->copy()->addDays(8), 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.labour_day'), 'month' => 5, 'day' => 1, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.victory_day'), 'month' => 5, 'day' => 9, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.childrens_day'), 'month' => 6, 'day' => 1, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.independence_day'), 'month' => 8, 'day' => 27, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.language_day'), 'month' => 8, 'day' => 31, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.chisinau_day'), 'month' => 10, 'day' => 14, 'length' => 1],
                ['name' => __('panel.holiday_planner.legal.christmas_new'), 'month' => 12, 'day' => 25, 'length' => 1],
            ];

            foreach ($yearly as $entry) {
                $start = array_key_exists('date', $entry)
                    ? $entry['date']->copy()
                    : Carbon::create($year, $entry['month'], $entry['day']);

                if ($start->lt($from) || $start->gt($to)) {
                    continue;
                }

                $candidates[] = [
                    'name' => (string) $entry['name'],
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $entry['length'] > 1 ? $start->copy()->addDays($entry['length'] - 1)->toDateString() : null,
                ];
            }
        }

        usort($candidates, fn (array $a, array $b): int => strcmp($a['starts_on'], $b['starts_on']));

        return $candidates;
    }

    /**
     * Creează candidații ceruți (chei `starts_on|name`), sărind peste cei deja existenți.
     *
     * @param  list<string>  $selectedKeys
     */
    public function create(Carbon $from, Carbon $to, array $selectedKeys): int
    {
        $created = 0;

        foreach ($this->candidatesBetween($from, $to) as $candidate) {
            if (! in_array($candidate['starts_on'].'|'.$candidate['name'], $selectedKeys, true)) {
                continue;
            }

            $exists = Holiday::query()
                ->where('name', $candidate['name'])
                ->whereDate('starts_on', $candidate['starts_on'])
                ->exists();

            if ($exists) {
                continue;
            }

            Holiday::create([
                'name' => $candidate['name'],
                'type' => HolidayType::LegalHoliday,
                'starts_on' => $candidate['starts_on'],
                'ends_on' => $candidate['ends_on'],
                'note' => __('panel.holiday_planner.legal.note'),
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Duminica Paștelui ortodox, în calendarul gregorian. Computusul Meeus dă data IULIANĂ;
     * pentru 1900–2099 decalajul iulian→gregorian e constant, 13 zile.
     */
    public function orthodoxEaster(int $year): Carbon
    {
        $a = $year % 4;
        $b = $year % 7;
        $c = $year % 19;
        $d = (19 * $c + 15) % 30;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->addDays(13);
    }
}
