<?php

namespace App\Filament\Pages;

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarAggregator;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Calendarul INSTITUȚIONAL pentru staff (modul Calendar). Consumă același {@see CalendarAggregator}
 * ca și cabinetul, cu scope de staff: doar evenimente globale (structură + sesiuni publicate +
 * evenimente/ședințe manuale), fără PII per-elev. Vederi: Lună / Săptămână / Zi / Agendă.
 */
class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar';

    protected static ?int $navigationSort = -2;

    public string $month = '';

    public string $mode = 'month';

    public string $focus = '';

    public function mount(): void
    {
        $now = Carbon::now();
        $this->month = $now->format('Y-m');
        $this->focus = $now->toDateString();
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
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return [];
        }

        [$from, $to] = $this->loadedRange();
        $scope = app(CalendarAccess::class)->staffScope($viewer);
        $items = app(CalendarAggregator::class)->collect($scope, $from, $to);

        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->date][] = $item->toArray();
        }

        ksort($grouped);

        return $grouped;
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
