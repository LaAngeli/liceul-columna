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
 * ca și cabinetul, dar cu scope de staff: doar evenimentele globale (structură — semestre/vacanțe — +
 * sesiuni de corigență publicate + viitoarele evenimente/ședințe manuale), fără PII per-elev la scară.
 */
class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar';

    protected static ?int $navigationSort = -2;

    public string $month = '';

    public function mount(): void
    {
        $this->month = Carbon::now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->baseMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->baseMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->month = Carbon::now()->format('Y-m');
    }

    public function monthLabel(): string
    {
        return $this->baseMonth()->translatedFormat('F Y');
    }

    /**
     * Evenimentele instituționale ale lunii, grupate și sortate pe zi (Y-m-d).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function eventsByDay(): array
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return [];
        }

        $base = $this->baseMonth();
        $scope = app(CalendarAccess::class)->staffScope($viewer);
        $items = app(CalendarAggregator::class)->collect($scope, $base->copy()->startOfMonth(), $base->copy()->endOfMonth());

        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->date][] = $item->toArray();
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Gridul lunii: celule (sau null pentru spațiile dinainte/după lună), cu evenimentele fiecărei zile.
     *
     * @return list<array{day: int, date: string, isToday: bool, events: list<array<string, mixed>>}|null>
     */
    public function monthCells(): array
    {
        $base = $this->baseMonth();
        $byDay = $this->eventsByDay();
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

    private function baseMonth(): Carbon
    {
        return (Carbon::createFromFormat('Y-m', $this->month) ?: Carbon::now())->startOfMonth();
    }
}
