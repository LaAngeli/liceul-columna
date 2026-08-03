<?php

namespace App\Http\Controllers;

use App\Models\CanteenMenu;
use App\Support\CanteenWeek;
use App\Support\SchoolCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina „Meniul cantinei" din cabinet (cerință 2026-08-01): consultare pentru TOATĂ familia.
 * Sursa e aceeași cu a panoului (canteen_menus, scrisă de administratorul operațional) și se
 * citește FĂRĂ cache — o salvare din panou se vede aici la următorul request.
 *
 * AXA TEMPORALĂ e cea a panoului (cerință 2026-08-04): aceleași cinci moduri, aceiași parametri de
 * URL (`mod`/`ref`/`de`/`pana`) și aceeași semantică — Zi/Săptămână/Lună arată grila CU zilele
 * nepublicate, Toate/Personalizat doar zilele CU meniu, recente întâi. Regula trăiește o singură
 * dată, în {@see CanteenWeek::period()}, de unde o ia și planificatorul din panou: două
 * implementări ar fi divergat, iar familia și administrația ar fi văzut perioade diferite.
 *
 * Navigarea rămâne pe LINKURI reale (nu stare de client): perioada e adresabilă, deci se poate
 * pune la favorite și trimite mai departe.
 *
 * Personalul consultă meniul în panoul lui; gardul EnsureFamilyCabinet de pe rută îl trimite acolo.
 */
class CabinetCanteenController extends Controller
{
    /** Modurile barei, în ordinea de afișare. „toate" e valoare explicită — vezi `mode()`. */
    private const MODES = ['toate', 'zi', 'saptamana', 'luna', 'personalizat'];

    public function index(Request $request): Response
    {
        $mode = $this->mode($request);
        $ref = $this->reference($request);
        $from = $this->date($request->query('de'));
        $until = $this->date($request->query('pana'));

        $period = CanteenWeek::period($mode, $ref->toDateString(), $from, $until);
        $today = SchoolCalendar::localNow()->startOfDay();

        return Inertia::render('cabinet/meniu', [
            'period' => [
                'mode' => $mode,
                // Etichetele vin de pe server, din ACELEAȘI chei ca bara panoului — un singur
                // vocabular pentru aceeași funcție, în ambele suprafețe.
                'pills' => array_map(fn (string $key): array => [
                    'key' => $key,
                    'label' => (string) __('panel.homework_time.'.($key === 'toate' ? 'all' : $key)),
                    'href' => $this->url($key, $key === $mode ? $ref : null),
                    'active' => $key === $mode,
                ], self::MODES),
                'label' => $this->periodLabel($mode, $ref),
                // Săgețile au sens doar pe perioadele ancorate; arhiva n-are „următoarea".
                'prev' => $this->step($mode, $ref, -1),
                'next' => $this->step($mode, $ref, 1),
                'todayHref' => $this->url($mode, $today),
                'isCurrent' => $this->covers($mode, $ref, $today),
                'from' => $from,
                'until' => $until,
                'labels' => [
                    'aria' => (string) __('panel.homework_time.aria'),
                    'prev' => (string) __('panel.homework_time.prev'),
                    'next' => (string) __('panel.homework_time.next'),
                    'today' => (string) __('panel.homework_time.today'),
                    'from' => (string) __('panel.homework_time.from'),
                    'until' => (string) __('panel.homework_time.until'),
                    'customHint' => (string) __('panel.homework_time.custom_hint'),
                ],
            ],
            'groups' => array_map(fn (array $group): array => [
                'label' => $group['label'],
                'days' => array_map(
                    fn (array $day): array => [
                        ...$day,
                        'menu' => $day['menu'] !== null ? $this->menuPayload($day['menu']) : null,
                    ],
                    $group['days'],
                ),
            ], $period['groups']),
            'today' => $today->toDateString(),
        ]);
    }

    /**
     * Modul activ. Implicit „săptămâna" — acolo se uită familia în mod normal, la fel ca
     * planificatorul din panou; „toate" rămâne o alegere explicită, altfel n-ar supraviețui
     * unui reload. Un `?mod` necunoscut cade pe implicit, nu pe eroare.
     */
    private function mode(Request $request): string
    {
        $mode = (string) $request->query('mod', '');

        return in_array($mode, self::MODES, true) ? $mode : 'saptamana';
    }

    /** Ancora perioadei: `ref`, cu `data` acceptat ca alias vechi (linkuri deja trimise). */
    private function reference(Request $request): Carbon
    {
        $raw = $this->date($request->query('ref')) ?? $this->date($request->query('data'));

        return $raw !== null
            ? Carbon::parse($raw, SchoolCalendar::TIMEZONE)->startOfDay()
            : SchoolCalendar::localNow()->startOfDay();
    }

    /** O dată din query, doar dacă e chiar o dată: altfel null (nu excepție). */
    private function date(mixed $raw): ?string
    {
        return is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1 ? $raw : null;
    }

    /** URL-ul paginii pentru un mod + ancoră — perioada rămâne adresabilă. */
    private function url(string $mode, ?Carbon $ref): string
    {
        return route('cabinet.canteen', array_filter([
            'mod' => $mode === 'saptamana' ? null : $mode,
            'ref' => $ref?->toDateString(),
        ]));
    }

    /** Perioada vecină (◀ ▶), în pasul modului. Null pe modurile fără ancoră. */
    private function step(string $mode, Carbon $ref, int $direction): ?string
    {
        $target = match ($mode) {
            'zi' => $ref->copy()->addDays($direction),
            'saptamana' => $ref->copy()->addWeeks($direction),
            'luna' => $ref->copy()->addMonthsNoOverflow($direction),
            default => null,
        };

        return $target !== null ? $this->url($mode, $target) : null;
    }

    /** Eticheta perioadei active („joi, 6 august 2026" / „3–9 aug. 2026" / „august 2026"). */
    private function periodLabel(string $mode, Carbon $ref): string
    {
        return match ($mode) {
            'zi' => ucfirst($ref->isoFormat('dddd, D MMMM YYYY')),
            'saptamana' => $ref->copy()->startOfWeek(Carbon::MONDAY)->isoFormat('D MMM')
                .' – '.$ref->copy()->endOfWeek(Carbon::SUNDAY)->isoFormat('D MMM YYYY'),
            'luna' => ucfirst($ref->isoFormat('MMMM YYYY')),
            default => '',
        };
    }

    /** Perioada afișată conține ziua de azi? (dezactivează „Azi", ca în panou). */
    private function covers(string $mode, Carbon $ref, Carbon $today): bool
    {
        return match ($mode) {
            'zi' => $ref->isSameDay($today),
            'saptamana' => $today->betweenIncluded($ref->copy()->startOfWeek(Carbon::MONDAY), $ref->copy()->endOfWeek(Carbon::SUNDAY)),
            'luna' => $ref->isSameMonth($today),
            default => true,
        };
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
