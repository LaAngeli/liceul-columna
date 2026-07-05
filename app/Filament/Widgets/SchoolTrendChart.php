<?php

namespace App\Filament\Widgets;

use App\Models\Absence;
use App\Models\Grade;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * „Activitate catalog" (varianta V-F): SECȚIUNE STANDARD a dashboard-ului de staff — aceeași
 * structură pentru toți, dar SCOPATĂ pe datele fiecăruia:
 *   • conducere / operațional / super-admin → activitatea întregii școli;
 *   • profesor / diriginte → PROPRIA activitate (notele introduse de el + absențele claselor lui).
 * Administratorul TEHNIC nu apare (fără fișă de profesor și fără rol academic → nicio amprentă).
 *
 * Note introduse + absențe înregistrate. Importul legacy (query builder) NU declanșează observers,
 * deci graficul reflectă ritmul introducerii curente. Perioadă selectabilă (filtru nativ): 1 / 3 / 6
 * luni; la 1 lună bucketing SĂPTĂMÂNAL (4 intervale — linie citibilă), la 3/6 luni LUNAR.
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

        // Standard pentru tot staff-ul cu amprentă în catalog: conducere/operațional, super-admin
        // (omniscient) SAU orice profesor/diriginte (are fișă). Administratorul TEHNIC — fără fișă și
        // fără rol academic — rămâne exclus (nicio activitate de catalog de arătat).
        return $user instanceof User
            && ($user->isManagement() || $user->isSuperAdmin() || $user->teacher !== null);
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
        $user = auth('web')->user();
        $schoolWide = $user instanceof User && ($user->isManagement() || $user->isSuperAdmin());
        $teacher = $user instanceof User ? $user->teacher : null;

        // Profesor (non-conducere): scop pe amprenta lui — notele introduse de el + absențele claselor
        // lui. Conducerea/super-adminul (schoolWide) văd școala întreagă (fără filtru).
        $restrict = ! $schoolWide && $teacher !== null;
        $teacherId = $teacher?->id;
        $classIds = $teacher?->visibleSchoolClassIds() ?? [];

        $gradesData = [];
        $absencesData = [];
        $labels = [];

        foreach ($this->buckets() as [$start, $end, $label]) {
            $labels[] = $label;

            $gradesData[] = Grade::query()
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('annulled_at')
                ->when($restrict, fn (Builder $q) => $q->where('teacher_id', $teacherId))
                ->count();

            $absencesData[] = Absence::query()
                ->whereBetween('created_at', [$start, $end])
                ->when($restrict, fn (Builder $q) => $q->whereIn('school_class_id', $classIds))
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
