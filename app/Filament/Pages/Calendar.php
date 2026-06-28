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

    private function baseMonth(): Carbon
    {
        return (Carbon::createFromFormat('Y-m', $this->month) ?: Carbon::now())->startOfMonth();
    }
}
