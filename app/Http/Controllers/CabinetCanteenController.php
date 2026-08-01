<?php

namespace App\Http\Controllers;

use App\Models\CanteenMenu;
use App\Support\SchoolCalendar;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina „Meniul cantinei" din cabinet (cerință 2026-08-01): consultare pentru TOATĂ familia,
 * navigabilă pe săptămâni, cu ziua curentă evidențiată. Sursa e aceeași cu a panoului
 * (canteen_menus, scrisă de administratorul operațional) și se citește FĂRĂ cache — o salvare din
 * panou se vede aici la următorul request.
 *
 * Personalul consultă meniul în panoul lui (resursa „Meniul cantinei"); gardul EnsureFamilyCabinet
 * de pe rută îl redirecționează acolo — fiecare rol pe suprafața lui, ambele peste aceleași date.
 */
class CabinetCanteenController extends Controller
{
    public function index(Request $request): Response
    {
        // Ancora săptămânii: `?data=YYYY-MM-DD` (orice zi din săptămâna dorită); lipsă sau
        // invalidă → azi, pe ORA ȘCOLII — la miezul nopții serverul UTC e încă „ieri".
        $today = SchoolCalendar::localNow()->startOfDay();
        $anchor = $this->anchorDate((string) $request->query('data'), $today);

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
            // aplicației (SetUserLocale rulează înaintea controllerului).
            $days[] = [
                'date' => $date->toDateString(),
                'label' => ucfirst($date->isoFormat('dddd, D MMMM')),
                'short' => ucfirst($date->isoFormat('dd D')),
                'isToday' => $date->isSameDay($today),
                'menu' => $menu !== null ? $this->menuPayload($menu) : null,
            ];
        }

        return Inertia::render('cabinet/meniu', [
            'week' => [
                'label' => $monday->isoFormat('D MMMM').' – '.$sunday->isoFormat('D MMMM YYYY'),
                'prev' => $monday->copy()->subWeek()->toDateString(),
                'next' => $monday->copy()->addWeek()->toDateString(),
                'isCurrent' => $today->betweenIncluded($monday, $sunday),
            ],
            'days' => $days,
            'today' => $today->toDateString(),
        ]);
    }

    /** Data-ancoră validată: un query param corupt cade pe azi, nu pe o excepție. */
    private function anchorDate(string $raw, Carbon $fallback): Carbon
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

    /**
     * Rubricile unei zile, în ordinea meniului oficial — doar cele completate, deja etichetate:
     * clientul primește perechi gata de afișat, nu chei de interpretat.
     *
     * @return array{breakfast: array<int, array{label: string, value: string}>, lunch: array<int, array{label: string, value: string}>, notes: string|null}
     */
    private function menuPayload(CanteenMenu $menu): array
    {
        $rows = fn (array $fields): array => collect($fields)
            ->filter(fn (string $field): bool => filled($menu->{$field}))
            ->map(fn (string $field): array => [
                'label' => (string) __('panel.forms.canteen.'.$field),
                'value' => (string) $menu->{$field},
            ])
            ->values()
            ->all();

        return [
            'breakfast' => $rows(CanteenMenu::breakfastFields()),
            'lunch' => $rows(CanteenMenu::lunchFields()),
            'notes' => $menu->notes,
        ];
    }
}
