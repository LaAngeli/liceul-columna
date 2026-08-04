<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use App\Models\CanteenMenu;
use App\Support\SchoolCalendar;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * ISTORICUL MENIURILOR (cerința beneficiarului, 04.08.2026).
 *
 * De când meniul se publică pe 25 și luna încheiată dispare din vederile curente
 * ({@see CanteenMenu::publicWindow()}), lunile trecute trebuie să rămână la îndemâna cuiva —
 * altfel „ascuns" ar însemna „pierdut". Aici stau TOATE lunile cu meniu, într-un tabel lunar
 * identic ca structură cu meniul tipărit al școlii (modelul trimis de beneficiar): un rând pe zi,
 * dejunul în patru rubrici, prânzul în șase. Se citește dintr-o privire, se poate tipări.
 *
 * ACCESUL e al administratorului operațional (+ super-admin, break-glass): el scrie meniul, deci
 * tot el are nevoie de arhivă. Restul personalului și familia rămân cu fereastra publică.
 *
 * Nu e o „a doua sursă": aceleași rânduri `canteen_menus`, doar altă lentilă — fără scope-ul de
 * vizibilitate, fiindcă privitorul e chiar autorul.
 */
class CanteenMenuHistory extends Page
{
    protected static string $resource = CanteenMenuResource::class;

    protected string $view = 'filament.canteen.history';

    /** Luna deschisă (Y-m). Trăiește în URL: o lună anume se poate trimite mai departe. */
    #[Url(as: 'luna', except: null)]
    public ?string $month = null;

    public function getTitle(): string
    {
        return __('panel.forms.canteen.history_title');
    }

    /** Arhiva e a celui care scrie meniul — ceilalți văd fereastra publică, nu istoricul. */
    public static function canAccess(array $parameters = []): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('panel.forms.canteen.history_back'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn (): string => CanteenMenuResource::getUrl('index')),
        ];
    }

    /**
     * Lunile cu meniu, recente întâi: eticheta lunii, câte zile are completate și dacă e luna
     * curentă / încă nepublicată — ca AO să vadă din listă unde e „acum" și ce urmează.
     *
     * @return list<array{key: string, label: string, days: int, state: 'archived'|'current'|'upcoming'}>
     */
    public function months(): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $currentKey = $today->format('Y-m');

        /** @var array<string, int> $counts */
        $counts = CanteenMenu::query()
            ->orderByDesc('menu_date')
            ->get(['menu_date'])
            ->groupBy(fn (CanteenMenu $menu): string => $menu->menu_date->format('Y-m'))
            ->map(fn ($group): int => $group->count())
            ->all();

        $months = [];

        foreach ($counts as $key => $days) {
            $months[] = [
                'key' => $key,
                'label' => ucfirst(Carbon::createFromFormat('Y-m-d', $key.'-01')->isoFormat('MMMM YYYY')),
                'days' => $days,
                'state' => match (true) {
                    $key === $currentKey => 'current',
                    $key > $currentKey => 'upcoming',
                    default => 'archived',
                },
            ];
        }

        // Recente întâi: și cronologic, și ca interes — arhiva se consultă de la capătul apropiat.
        usort($months, fn (array $a, array $b): int => strcmp($b['key'], $a['key']));

        return $months;
    }

    /**
     * Luna deschisă: cea din URL dacă are meniuri, altfel ULTIMA LUNĂ ÎNCHEIATĂ — arhiva se
     * deschide pe ce tocmai a dispărut din vederile curente, adică pe motivul pentru care intri.
     */
    public function activeMonth(): ?string
    {
        $months = $this->months();

        if ($months === []) {
            return null;
        }

        $keys = array_column($months, 'key');

        if ($this->month !== null && in_array($this->month, $keys, true)) {
            return $this->month;
        }

        $archived = array_values(array_filter($months, fn (array $m): bool => $m['state'] === 'archived'));

        return $archived === [] ? $months[0]['key'] : $archived[0]['key'];
    }

    public function openMonth(string $month): void
    {
        $this->month = $month;
    }

    /**
     * Rândurile lunii deschise, în forma tabelului tipărit: o zi pe rând, cu ziua săptămânii și
     * cele zece rubrici. Zilele fără meniu NU apar — tabelul e o consemnare a ce s-a servit.
     *
     * @return list<array{date: string, weekday: string, iso: string, breakfast: list<string|null>, lunch: list<string|null>, notes: string|null, editUrl: string}>
     */
    public function rows(): array
    {
        $month = $this->activeMonth();

        if ($month === null) {
            return [];
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();

        /** @var list<array{date: string, weekday: string, iso: string, breakfast: list<string|null>, lunch: list<string|null>, notes: string|null, editUrl: string}> $rows */
        $rows = CanteenMenu::query()
            ->whereBetween('menu_date', [$start, $start->copy()->endOfMonth()->endOfDay()])
            ->orderBy('menu_date')
            ->get()
            ->map(fn (CanteenMenu $menu): array => [
                'date' => $menu->menu_date->format('d.m.Y'),
                'weekday' => ucfirst($menu->menu_date->isoFormat('dddd')),
                'iso' => $menu->menu_date->toDateString(),
                'breakfast' => array_map(fn (string $field): ?string => $menu->{$field}, CanteenMenu::breakfastFields()),
                'lunch' => array_map(fn (string $field): ?string => $menu->{$field}, CanteenMenu::lunchFields()),
                'notes' => $menu->notes,
                'editUrl' => CanteenMenuResource::getUrl('edit', ['record' => $menu]),
            ])
            ->values()
            ->all();

        return $rows;
    }

    /** Eticheta lunii deschise, pentru antetul tabelului. */
    public function activeMonthLabel(): ?string
    {
        $month = $this->activeMonth();

        return $month === null
            ? null
            : ucfirst(Carbon::createFromFormat('Y-m-d', $month.'-01')->isoFormat('MMMM YYYY'));
    }
}
