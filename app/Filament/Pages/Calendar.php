<?php

namespace App\Filament\Pages;

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarAggregator;
use App\Enums\CalendarCategory;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Calendarul INSTITUȚIONAL pentru staff (modul Calendar). Consumă același {@see CalendarAggregator}
 * ca și cabinetul, cu scope de staff. Vederi: Lună / Săptămână / Zi / Agendă.
 *
 * ⚠️ DECIZIE (2026-07-12): transparent INTERN („staff-wide") — tot personalul academic vede TOATE
 * evenimentele manuale, inclusiv cele scoped pe clasă/treaptă. Scope-ul controlează ce văd FAMILIILE
 * în cabinet, nu confidențialitatea între colegi → fără PII de elev în titluri/descrieri.
 */
class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    // Grupul „Comunicare", între Mesaje (10) și Evenimente (30) — vederea agregată a calendarului
    // stă alături de sursa lui (CalendarEvents = intrări individuale).
    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    // Calendar instituțional = staff academic (conducere + personal pedagogic). Administratorul
    // tehnic e exclus (infra, fără date academice) — audit #33, decizia „AT = doar agregate".
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public function getTitle(): string
    {
        return __('panel.pages.calendar.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.calendar.title');
    }

    public string $month = '';

    public string $mode = 'month';

    public string $focus = '';

    /**
     * Cheile categoriilor vizibile (filtru chip-toggle). Implicit toate sunt vizibile;
     * un click pe chip îl scoate / repune. Filtrarea se aplică în byDay().
     *
     * @var list<string>
     */
    public array $visibleCategories = [];

    /** ID-ul evenimentului deschis în modal (CalendarItem::id), sau null când modalul e închis. */
    public ?string $selectedEventId = null;

    public function mount(): void
    {
        $now = Carbon::now();
        $this->month = $now->format('Y-m');
        $this->focus = $now->toDateString();
        $this->visibleCategories = array_map(fn (CalendarCategory $c): string => $c->value, CalendarCategory::cases());
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['month', 'week', 'day', 'agenda'], true)) {
            $this->mode = $mode;
        }
    }

    public function previous(): void
    {
        $this->shift(-1);
    }

    public function next(): void
    {
        $this->shift(1);
    }

    public function goToday(): void
    {
        $now = Carbon::now();
        $this->month = $now->format('Y-m');
        $this->focus = $now->toDateString();
    }

    public function openDay(string $date): void
    {
        $this->focus = $date;
        $this->mode = 'day';
        $this->month = Carbon::parse($date)->format('Y-m');
    }

    /**
     * Toggle vizibilitatea unei categorii din filtrul de chip-uri. Cel puțin una rămâne activă
     * (apăsând pe ultima activă o re-aprinde, nu lasă calendarul gol).
     */
    public function toggleCategory(string $key): void
    {
        if (! in_array($key, $this->visibleCategories, true)) {
            $this->visibleCategories[] = $key;

            return;
        }

        $next = array_values(array_filter($this->visibleCategories, static fn (string $v): bool => $v !== $key));

        if ($next === []) {
            return;
        }

        $this->visibleCategories = $next;
    }

    public function showAllCategories(): void
    {
        $this->visibleCategories = array_map(static fn (CalendarCategory $c): string => $c->value, CalendarCategory::cases());
    }

    public function selectEvent(string $id): void
    {
        $this->selectedEventId = $id;
    }

    public function closeEvent(): void
    {
        $this->selectedEventId = null;
    }

    /**
     * Caută evenimentul selectat în lista de items încărcate. Null dacă nu mai e în range (după
     * navigare prev/next, modalul se închide automat la următoarea redare).
     *
     * @return array<string, mixed>|null
     */
    public function selectedEvent(): ?array
    {
        if ($this->selectedEventId === null) {
            return null;
        }

        foreach ($this->byDay() as $events) {
            foreach ($events as $event) {
                if (($event['id'] ?? null) === $this->selectedEventId) {
                    return $event;
                }
            }
        }

        return null;
    }

    /**
     * Poate utilizatorul curent să creeze evenimente de calendar (conducere ●; diriginți doar
     * pentru clasele lor — același gating ca pe CalendarEventResource).
     */
    public function canAddEvent(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canManageCalendarEvents();
    }

    public function addEventUrl(): string
    {
        return CalendarEventResource::getUrl('create');
    }

    public function periodTitle(): string
    {
        if ($this->mode === 'day') {
            return Carbon::parse($this->focus)->translatedFormat('l, j F Y');
        }

        if ($this->mode === 'week') {
            $start = Carbon::parse($this->focus)->startOfWeek(Carbon::MONDAY);

            return $start->translatedFormat('j M').' – '.$start->copy()->addDays(6)->translatedFormat('j M Y');
        }

        return $this->baseMonth()->translatedFormat('F Y');
    }

    /**
     * Gridul lunii: celule (sau null), cu evenimentele fiecărei zile.
     *
     * @return list<array{day: int, date: string, isToday: bool, events: list<array<string, mixed>>}|null>
     */
    public function monthCells(): array
    {
        $base = $this->baseMonth();
        $byDay = $this->byDay();
        $todayStr = Carbon::now()->toDateString();

        $lead = $base->copy()->startOfMonth()->dayOfWeekIso - 1;
        $daysInMonth = (int) $base->copy()->endOfMonth()->format('j');

        $cells = [];

        for ($i = 0; $i < $lead; $i++) {
            $cells[] = null;
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $base->copy()->day($d)->toDateString();
            $cells[] = [
                'day' => $d,
                'date' => $date,
                'isToday' => $date === $todayStr,
                'events' => $byDay[$date] ?? [],
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return $cells;
    }

    /**
     * Cele 7 zile ale săptămânii curente (luni–duminică), cu evenimentele lor.
     *
     * @return list<array{date: string, weekday: string, day: int, isToday: bool, events: list<array<string, mixed>>}>
     */
    public function weekDays(): array
    {
        $byDay = $this->byDay();
        $start = Carbon::parse($this->focus)->startOfWeek(Carbon::MONDAY);
        $todayStr = Carbon::now()->toDateString();

        $out = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $date = $day->toDateString();
            $out[] = [
                'date' => $date,
                'weekday' => $day->translatedFormat('D'),
                'day' => $day->day,
                'isToday' => $date === $todayStr,
                'events' => $byDay[$date] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Evenimentele zilei aflate în focus (vederea Zi).
     *
     * @return list<array<string, mixed>>
     */
    public function dayEvents(): array
    {
        return $this->byDay()[$this->focus] ?? [];
    }

    /**
     * Evenimentele instituționale ale intervalului încărcat, grupate și sortate pe zi (Y-m-d).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function byDay(): array
    {
        $viewer = auth('web')->user();

        if (! $viewer instanceof User) {
            return [];
        }

        [$from, $to] = $this->loadedRange();
        $scope = app(CalendarAccess::class)->staffScope($viewer);
        $items = app(CalendarAggregator::class)->collect($scope, $from, $to);

        $grouped = [];

        foreach ($items as $item) {
            // Filtru pe categorii vizibile (chip-uri toggle din UI). Dacă lista e goală, nimic
            // nu trece — dar `toggleCategory()` refuză să golească lista complet.
            if (! in_array($item->category->value, $this->visibleCategories, true)) {
                continue;
            }

            $grouped[$item->date][] = $item->toArray();
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Lista de chip-uri pentru filtrul de categorii: pentru fiecare categorie din taxonomie,
     * eticheta + cheia de culoare + dacă e activă acum (`isActive`).
     *
     * @return list<array{key: string, label: string, color: string, isActive: bool}>
     */
    public function categoryChips(): array
    {
        $chips = [];

        foreach (CalendarCategory::cases() as $category) {
            $chips[] = [
                'key' => $category->value,
                'label' => $category->getLabel(),
                'color' => $category->color(),
                'isActive' => in_array($category->value, $this->visibleCategories, true),
            ];
        }

        return $chips;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function loadedRange(): array
    {
        $base = $this->baseMonth();

        return [
            $base->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
            $base->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function shift(int $dir): void
    {
        if ($this->mode === 'week') {
            $this->focus = Carbon::parse($this->focus)->addDays($dir * 7)->toDateString();
            $this->month = Carbon::parse($this->focus)->format('Y-m');

            return;
        }

        if ($this->mode === 'day') {
            $this->focus = Carbon::parse($this->focus)->addDays($dir)->toDateString();
            $this->month = Carbon::parse($this->focus)->format('Y-m');

            return;
        }

        $base = $this->baseMonth()->addMonthsNoOverflow($dir);
        $this->month = $base->format('Y-m');
        $this->focus = $base->toDateString();
    }

    private function baseMonth(): Carbon
    {
        return (Carbon::createFromFormat('Y-m', $this->month) ?: Carbon::now())->startOfMonth();
    }
}
