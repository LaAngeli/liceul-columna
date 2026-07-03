<?php

namespace App\Filament\Widgets;

use App\Models\Absence;
use App\Models\Grade;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Tendința activității din catalog pe ultimele 6 luni (note introduse + absențe înregistrate),
 * pentru conducerea școlii. Importul legacy (query builder) NU declanșează observers — datele sunt
 * dominate de introducerea curentă, deci graficul reflectă cu adevărat ritmul lunii.
 *
 * Pe panou nu există încă un widget de „evoluție în timp" (toate stats-urile sunt cumulative) —
 * acest chart e contrabalansul. Sort 0 = sub overviews/queue widgets, peste AudiencesPendingAssignment.
 */
class SchoolTrendChart extends ChartWidget
{
    protected static ?int $sort = 0;

    public function getHeading(): string
    {
        return (string) __('panel.widgets.school_trend_chart.heading');
    }

    protected int|string|array $columnSpan = 'full';

    // Polling rar (5 min): datele istorice schimbă lent; agregarea face 12 queries (6 luni × 2 modele).
    protected ?string $pollingInterval = '5m';

    public static function canView(): bool
    {
        return auth('web')->user()?->isManagement() ?? false;
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $months = [];
        $gradesData = [];
        $absencesData = [];
        $labels = [];

        $now = Carbon::now();
        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $months[] = $start;
            $start->locale(app()->getLocale());
            $labels[] = $start->isoFormat('MMM YYYY');

            $gradesData[] = Grade::query()
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('annulled_at')
                ->count();

            $absencesData[] = Absence::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('panel.widgets.school_trend_chart.grades_dataset'),
                    'data' => $gradesData,
                    'borderColor' => '#0f4d77', // navy de brand
                    'backgroundColor' => 'rgba(15, 77, 119, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => __('panel.widgets.school_trend_chart.absences_dataset'),
                    'data' => $absencesData,
                    'borderColor' => '#9bc31e', // verde de brand
                    'backgroundColor' => 'rgba(155, 195, 30, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
