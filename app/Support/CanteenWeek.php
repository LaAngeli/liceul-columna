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
            ->whereBetween('menu_date', [$monday->copy()->startOfDay(), $sunday->copy()->endOfDay()])
            ->get()
            ->keyBy(fn (CanteenMenu $menu): string => $menu->menu_date->toDateString());

        // Luni–vineri mereu; weekendul doar dacă are meniu (cantina nu gătește sâmbăta, dar
        // structura nu interzice — o zi cu date nu are voie să dispară din afișare).
        $days = [];

        foreach (range(0, 6) as $offset) {
            $date = $monday->copy()->addDays($offset);
            $menu = $menus->get($date->toDateString());

            if ($offset >= 5 && $menu === null) {
                continue;
            }

            // isoFormat e deja în limba sesiunii — Laravel ține locale-ul Carbon sincron cu al
            // aplicației.
            $days[] = [
                'date' => $date->toDateString(),
                'label' => ucfirst($date->isoFormat('dddd, D MMMM')),
                'short' => ucfirst($date->isoFormat('dd D')),
                'isToday' => $date->isSameDay($today),
                'menu' => $menu,
            ];
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
