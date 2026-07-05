<?php

namespace App\Filament\Widgets;

use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Message;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Carbon;

/**
 * „Monitor activitate" — monitorul PERSONAL de activitate al oricărui membru al staff-ului (userul
 * logat), SECȚIUNE STANDARD a dashboard-ului. O linie principală „Activitate totală" + linii pe
 * categorie, toggle-abile prin bifă. Sursă ENTITY-BASED (coloane de atribuire pe rând), deliberat
 * FĂRĂ jurnalul de audit: auditul amestecă acțiunea umană cu recalcule de sistem (TermAverage scris
 * din queue cu user_id NULL) și nu acoperă GradeCorrection/Message (care nu sunt Auditable).
 *
 * Fiecare serie = numărul de acțiuni ale userului pe interval (axa Y = acțiuni, întregi):
 *   • Note      — note ACTIVE introduse de el (Grade.teacher_id, la created_at);
 *   • Absențe   — absențe consemnate în clasele lui (Absence.school_class_id ∈ visibleSchoolClassIds);
 *   • Corecții  — corecții cerute (requested_by, created_at) + revizuite (reviewed_by, reviewed_at);
 *   • Motivări  — motivări de absență revizuite de el (reviewed_by, reviewed_at);
 *   • Mesaje    — mesaje trimise de el (sender_user_id, created_at).
 * „Total" = suma categoriilor AFIȘATE per interval → tot ce e pe ecran se însumează exact (axa devine
 * lizibilă). Perioadă selectabilă 1/3/6 luni; la 1 lună bucketing săptămânal, la 3/6 lunar.
 */
class ActivityMonitor extends ChartWidget
{
    use HasFiltersSchema;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    // Interogări ușoare (max 6 buckets × 5 categorii, indexate) → reîmprospătare rară e suficientă.
    protected ?string $pollingInterval = '5m';

    /** Categoriile toggle-abile (fără „total", care e derivat și mereu desenat). */
    private const CATEGORY_KEYS = ['grades', 'absences', 'corrections', 'motivations', 'messages'];

    /** Bifate implicit — cele două acțiuni de bază ale unui profesor. */
    private const DEFAULT_SERIES = ['grades', 'absences'];

    /** Paletă ancorată în brand (navy/verde/warm-dark + tente/nuanțe + gri). Separare pe luminozitate. */
    private const COLORS = [
        'total' => '#0f4d77',        // navy primar — linie de referință
        'grades' => '#9bc31e',       // verde accent
        'absences' => '#2e2d2c',     // warm-dark
        'corrections' => '#3d82b8',  // tentă de navy
        'motivations' => '#5f7a13',  // nuanță de verde (olive)
        'messages' => '#686867',     // gri de brand
    ];

    public static function canView(): bool
    {
        // Secțiune standard pentru TOT staff-ul: fiecare își vede propria activitate (personală).
        return auth('web')->check();
    }

    public function getHeading(): string
    {
        return __('panel.widgets.activity_monitor.heading_base')
            .' — '.self::monthsLabel($this->periodMonths());
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->label(__('panel.widgets.activity_monitor.filter_period'))
                ->options([
                    '1' => self::monthsLabel(1),
                    '3' => self::monthsLabel(3),
                    '6' => self::monthsLabel(6),
                ])
                ->default('6')
                ->grouped(),
            CheckboxList::make('series')
                ->label(__('panel.widgets.activity_monitor.filter_series'))
                ->options($this->seriesOptions())
                ->default(self::DEFAULT_SERIES)
                ->columns(3)
                ->gridDirection(GridDirection::Row)
                ->bulkToggleable(),
        ]);
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $selected = $this->selectedSeries();

        $user = auth('web')->user();
        $teacher = $user instanceof User ? $user->teacher : null;
        $teacherId = $teacher?->id;
        $classIds = $teacher?->visibleSchoolClassIds() ?? [];
        $userId = $user?->getKey();

        $labels = [];
        $totalData = [];
        /** @var array<string, list<int>> $categoryData */
        $categoryData = array_fill_keys($selected, []);

        foreach ($this->buckets() as [$start, $end, $label]) {
            $labels[] = $label;
            $bucketTotal = 0;

            foreach ($selected as $key) {
                $count = $this->categoryCount($key, $start, $end, $teacherId, $classIds, $userId);
                $categoryData[$key][] = $count;
                $bucketTotal += $count;
            }

            $totalData[] = $bucketTotal;
        }

        $datasets = [$this->dataset('total', $totalData, 3)];
        foreach ($selected as $key) {
            $datasets[] = $this->dataset($key, $categoryData[$key], 2);
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0], // acțiuni = numere întregi
                    'title' => [
                        'display' => true,
                        'text' => __('panel.widgets.activity_monitor.axis_y'),
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * Numărul de acțiuni ale userului pentru o categorie, într-un interval. Fiecare categorie e
     * datată la momentul REAL al acțiunii (created_at pentru introduceri, reviewed_at pentru revizuiri).
     *
     * @param  list<int>  $classIds
     */
    private function categoryCount(string $key, Carbon $start, Carbon $end, ?int $teacherId, array $classIds, ?int $userId): int
    {
        return match ($key) {
            'grades' => $teacherId === null ? 0 : Grade::query()
                ->active()
                ->where('teacher_id', $teacherId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'absences' => $classIds === [] ? 0 : Absence::query()
                ->whereIn('school_class_id', $classIds)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'corrections' => $userId === null ? 0 : GradeCorrection::query()
                ->where('requested_by_user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->count()
                + GradeCorrection::query()
                    ->where('reviewed_by_user_id', $userId)
                    ->whereBetween('reviewed_at', [$start, $end])
                    ->count(),
            'motivations' => $userId === null ? 0 : AbsenceMotivation::query()
                ->where('reviewed_by_user_id', $userId)
                ->whereBetween('reviewed_at', [$start, $end])
                ->count(),
            'messages' => $userId === null ? 0 : Message::query()
                ->where('sender_user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            default => 0,
        };
    }

    /**
     * @param  list<int>  $data
     * @return array<string, mixed>
     */
    private function dataset(string $key, array $data, int $borderWidth): array
    {
        return [
            'label' => __("panel.widgets.activity_monitor.series.$key"),
            'data' => $data,
            'borderColor' => self::COLORS[$key],
            'backgroundColor' => self::COLORS[$key],
            'borderWidth' => $borderWidth,
            'fill' => false, // 6 linii cu fill = supă vizuală
            'tension' => 0.35,
        ];
    }

    /**
     * Seriile bifate, filtrate prin whitelist și readuse la ordinea canonică. Gol ≠ toate: dacă userul
     * debifează tot, rămâne doar linia „Total" (plată la 0) — nu reactivăm implicit toate seriile.
     *
     * @return list<string>
     */
    private function selectedSeries(): array
    {
        $selected = $this->filters['series'] ?? self::DEFAULT_SERIES;

        if (! is_array($selected)) {
            $selected = self::DEFAULT_SERIES;
        }

        return array_values(array_intersect(self::CATEGORY_KEYS, $selected));
    }

    /**
     * @return array<string, string>
     */
    private function seriesOptions(): array
    {
        $out = [];
        foreach (self::CATEGORY_KEYS as $key) {
            $out[$key] = __("panel.widgets.activity_monitor.series.$key");
        }

        return $out;
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
        $period = (int) ($this->filters['period'] ?? 6);

        return in_array($period, [1, 3, 6], true) ? $period : 6;
    }

    private static function monthsLabel(int $months): string
    {
        return trans_choice('panel.widgets.activity_monitor.months', $months, ['count' => $months]);
    }
}
