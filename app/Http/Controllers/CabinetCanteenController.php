<?php

namespace App\Http\Controllers;

use App\Models\CanteenMenu;
use App\Support\CanteenWeek;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina „Meniul cantinei" din cabinet (cerință 2026-08-01): consultare pentru TOATĂ familia,
 * navigabilă pe săptămâni, cu ziua curentă evidențiată. Sursa e aceeași cu a panoului
 * (canteen_menus, scrisă de administratorul operațional) și se citește FĂRĂ cache — o salvare din
 * panou se vede aici la următorul request. Săptămâna se construiește în {@see CanteenWeek},
 * partajat cu planificatorul din panou.
 *
 * Personalul consultă meniul în panoul lui (planificatorul „Meniul cantinei"); gardul
 * EnsureFamilyCabinet de pe rută îl redirecționează acolo.
 */
class CabinetCanteenController extends Controller
{
    public function index(Request $request): Response
    {
        $week = CanteenWeek::build((string) $request->query('data'));

        return Inertia::render('cabinet/meniu', [
            'week' => [
                'label' => $week['label'],
                'prev' => $week['monday']->copy()->subWeek()->toDateString(),
                'next' => $week['monday']->copy()->addWeek()->toDateString(),
                'isCurrent' => $week['isCurrent'],
            ],
            'days' => array_map(
                fn (array $day): array => [
                    ...$day,
                    'menu' => $day['menu'] !== null ? $this->menuPayload($day['menu']) : null,
                ],
                $week['days'],
            ),
            'today' => $week['today']->toDateString(),
        ]);
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
