<?php

namespace App\Filament\Widgets;

use App\Models\Absence;
use App\Models\Grade;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * „Activitate catalog" (varianta V-F): tendința activității din catalog — note introduse + absențe
 * înregistrate — pentru conducerea școlii + super-admin (break-glass). Importul legacy (query builder)
 * NU declanșează observers, deci graficul reflectă ritmul introducerii curente, nu volumul istoric.
 *
 * Perioadă selectabilă (filtru nativ ChartWidget): 1 / 3 / 6 luni. La 1 lună bucketing SĂPTĂMÂNAL
 * (4 intervale — o linie citibilă), la 3/6 luni bucketing LUNAR. Sort 0 = sub overviews/queue,
 * peste AudiencesPendingAssignment.
 */
class SchoolTrendChart extends ChartWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    // Polling rar (5 min): datele istorice schimbă lent; agregarea face până la 12 queries (6 luni × 2).
    protected ?string $pollingInterval = '5m';

    // Perioada implicită (luni). Cheile getFilters() = '1' | '3' | '6'.
    public ?string $filter = '6';

    public static function canView(): bool
    {
        $user = auth('web')->user();

        // Conducerea academică/operațională + super-adminul (omniscient). Administratorul TEHNIC e
        // exclus deliberat (fără acces la date academice, chiar și agregate).
        return $user !== null && ($user->isManagement() || $user->isSuperAdmin());
    }

    public function getHeading(): string
    {
        return __('panel.widgets.school_trend_chart.heading_base')
            .' — '.self::monthsLabel($this->periodMonths());
    }

    /**
     * Cheile numerice-string devin chei int (coerciție PHP) — filtrul le trimite înapoi ca string,
     * iar periodMonths() le normalizează cu (int), deci alegerea funcționează în ambele sensuri.
     *
     * @return array<int, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '1' => self::monthsLabel(1),
            '3' => self::monthsLabel(3),
            '6' => self::monthsLabel(6),
        ];
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $gradesData = [];
        $absencesData = [];
        $labels = [];

        foreach ($this->buckets() as [$start, $end, $label]) {
            $labels[] = $label;

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

    /**
     * Intervalele de agregare pentru perioada aleasă. La 1 lună → 4 săptămâni (altfel un singur punct
     * lunar = linie inutilă); la 3/6 luni → luni calendaristice. Fiecare interval: [start, end, etichetă].
     *
     * @return list<array{0: Carbon, 1: Carbon, 2: string}>
     */
    private function buckets(): array
    {
        $now = Carbon::now();
        $locale = app()->getLocale();
        $out = [];

        if ($this->periodMonths() === 1) {
            for ($i = 3; $i >= 0; $i--) {
                $start = $now->copy()->subWeeks($i)->startOfWeek();
                $end = $start->copy()->endOfWeek();
                $start->locale($locale);
                $out[] = [$start, $end, $start->isoFormat('D MMM')];
            }

            return $out;
        }

        for ($i = $this->periodMonths() - 1; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $start->locale($locale);
            $out[] = [$start, $end, $start->isoFormat('MMM YYYY')];
        }

        return $out;
    }

    /**
     * Perioada validată în luni (whitelist {1,3,6}) — apărare la valori arbitrare din filtrul live.
     */
    private function periodMonths(): int
    {
        return in_array((int) $this->filter, [1, 3, 6], true) ? (int) $this->filter : 6;
    }

    private static function monthsLabel(int $months): string
    {
        return trans_choice('panel.widgets.school_trend_chart.months', $months, ['count' => $months]);
    }
}
