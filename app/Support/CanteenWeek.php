<?php

namespace App\Support;

use App\Models\CanteenMenu;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Săptămâna de meniu — construită ÎNTR-UN singur loc pentru ambele suprafețe (planificatorul din
 * panou + pagina din cabinet), ca structura afișată să nu poată diverge: aceeași ancorare, aceeași
 * regulă de weekend, aceeași evidențiere a zilei curente (pe ORA ȘCOLII — la miezul nopții
 * serverul UTC e încă „ieri").
 *
 * @phpstan-type WeekDay array{date: string, label: string, short: string, isToday: bool, menu: CanteenMenu|null}
 */
final class CanteenWeek
{
    /**
     * @return array{
     *     monday: Carbon,
     *     sunday: Carbon,
     *     today: Carbon,
     *     isCurrent: bool,
     *     label: string,
     *     days: array<int, WeekDay>,
     * }
     */
    public static function build(?string $anchorRaw): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $anchor = self::anchorDate((string) $anchorRaw, $today);

        $monday = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->addDays(6);

        // Capetele ca zi ÎNTREAGĂ, nu ca șir de dată: pe SQLite (testele) coloana `date` se
        // stochează cu oră („2026-09-13 00:00:00"), iar „… <= 2026-09-13" ar pierde duminica.
        $menus = CanteenMenu::query()
            ->visible()
            ->whereBetween('menu_date', [$monday->copy()->startOfDay(), $sunday->copy()->endOfDay()])
            ->get()
            ->keyBy(fn (CanteenMenu $menu): string => $menu->menu_date->toDateString());

        // Luni–vineri mereu; weekendul doar dacă are meniu (cantina nu gătește sâmbăta, dar
        // structura nu interzice — o zi cu date nu are voie să dispară din afișare).
        // isoFormat e deja în limba sesiunii — Laravel ține locale-ul Carbon sincron cu al aplicației.
        $days = [];

        foreach (range(0, 6) as $offset) {
            $date = $monday->copy()->addDays($offset);
            $menu = $menus->get($date->toDateString());

            if ($offset >= 5 && $menu === null) {
                continue;
            }

            $days[] = self::dayStruct($date, $menu, $today);
        }

        return [
            'monday' => $monday,
            'sunday' => $sunday,
            'today' => $today,
            'isCurrent' => $today->betweenIncluded($monday, $sunday),
            'label' => $monday->isoFormat('D MMMM').' – '.$sunday->isoFormat('D MMMM YYYY'),
            'days' => $days,
        ];
    }

    /**
     * O SINGURĂ zi (modul „Zi" al planificatorului) — fără regula de weekend: ziua cerută se
     * arată chiar goală și sâmbăta, altfel modul ar afișa nimic fără nicio explicație.
     *
     * @return WeekDay
     */
    public static function day(?string $anchorRaw): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $date = self::anchorDate((string) $anchorRaw, $today);

        $menu = CanteenMenu::query()->visible()->whereDate('menu_date', $date->toDateString())->first();

        return self::dayStruct($date, $menu, $today);
    }

    /**
     * ARHIVA: doar zilele CU meniu dintr-un interval (capete opționale), grupate pe săptămâni,
     * cele recente întâi. E vederea de consultare a modurilor „Toate"/„Personalizat" — planificarea
     * (zile goale + butoane de adăugare) rămâne treaba modurilor Zi/Săptămână/Lună.
     *
     * @return array<int, array{label: string, days: array<int, WeekDay>}>
     */
    public static function archive(?string $from, ?string $until): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();

        $menus = CanteenMenu::query()
            ->visible()
            // whereDate pe capete: pe SQLite coloana `date` poartă și ora.
            ->when($from !== null, fn ($query) => $query->whereDate('menu_date', '>=', $from))
            ->when($until !== null, fn ($query) => $query->whereDate('menu_date', '<=', $until))
            ->orderBy('menu_date')
            ->get();

        return $menus
            ->groupBy(fn (CanteenMenu $menu): string => $menu->menu_date->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(function ($weekMenus, string $mondayKey) use ($today): array {
                $monday = Carbon::parse($mondayKey, SchoolCalendar::TIMEZONE);
                $sunday = $monday->copy()->addDays(6);

                return [
                    'label' => $monday->isoFormat('D MMMM').' – '.$sunday->isoFormat('D MMMM YYYY'),
                    'days' => $weekMenus
                        ->map(fn (CanteenMenu $menu): array => self::dayStruct(
                            Carbon::parse($menu->menu_date->toDateString(), SchoolCalendar::TIMEZONE),
                            $menu,
                            $today,
                        ))
                        ->values()
                        ->all(),
                ];
            })
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /**
     * PERIOADA cerută de bara temporală, în forma de afișare — sursa UNICĂ a semanticii celor cinci
     * moduri, folosită de planificatorul din panou ȘI de pagina din cabinet.
     *
     * Distincția care contează e `kind`:
     *   • `planning` (Zi / Săptămână / Lună) — grile CU zilele goale: se vede ce NU e încă publicat.
     *     Panoul pune butoane de adăugare pe zilele goale; cabinetul le arată doar ca „nepublicat".
     *   • `archive` (Toate / Personalizat) — doar zilele CU meniu, pe săptămâni, recente întâi:
     *     consultare, unde zilele goale ar fi zgomot.
     *
     * @param  string|null  $mode  null sau „toate" → arhiva completă
     * @return array{kind: 'planning'|'archive', groups: array<int, array{label: string|null, days: array<int, WeekDay>}>}
     */
    public static function period(?string $mode, ?string $ref, ?string $from, ?string $until): array
    {
        return match ($mode) {
            'zi' => ['kind' => 'planning', 'groups' => [['label' => null, 'days' => [self::day($ref)]]]],
            'saptamana' => ['kind' => 'planning', 'groups' => [['label' => null, 'days' => self::build($ref)['days']]]],
            'luna' => ['kind' => 'planning', 'groups' => self::monthWeeks($ref)],
            'personalizat' => ['kind' => 'archive', 'groups' => self::archive($from, $until)],
            default => ['kind' => 'archive', 'groups' => self::archive(null, null)],
        };
    }

    /**
     * Săptămânile care ating luna de referință — COMPLETE, chiar dacă un capăt iese din lună:
     * săptămâna e unitatea de lucru a cantinei, o jumătate de săptămână nu se planifică.
     *
     * @return array<int, array{label: string, days: array<int, WeekDay>}>
     */
    private static function monthWeeks(?string $ref): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $anchor = self::anchorDate((string) $ref, $today);

        $monday = $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $lastDay = $anchor->copy()->endOfMonth();

        $weeks = [];

        while ($monday->lessThanOrEqualTo($lastDay)) {
            $week = self::build($monday->toDateString());

            $weeks[] = ['label' => $week['label'], 'days' => $week['days']];
            $monday = $monday->copy()->addWeek();
        }

        return $weeks;
    }

    /**
     * O zi în forma comună de afișare.
     *
     * @return WeekDay
     */
    private static function dayStruct(Carbon $date, ?CanteenMenu $menu, Carbon $today): array
    {
        return [
            'date' => $date->toDateString(),
            'label' => ucfirst($date->isoFormat('dddd, D MMMM')),
            'short' => ucfirst($date->isoFormat('dd D')),
            'isToday' => $date->isSameDay($today),
            'menu' => $menu,
        ];
    }

    /** Data-ancoră validată: un query param corupt cade pe azi, nu pe o excepție. */
    private static function anchorDate(string $raw, Carbon $fallback): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return $fallback->copy();
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw, SchoolCalendar::TIMEZONE);
        } catch (InvalidFormatException) {
            return $fallback->copy();
        }

        return $parsed?->startOfDay() ?? $fallback->copy();
    }
}
