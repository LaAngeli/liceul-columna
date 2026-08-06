<?php

namespace App\Filament\Widgets;

use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Message;
use App\Models\User;
use App\Support\SchoolCalendar;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * „PULSUL ACTIVITĂȚII" — monitorul PERSONAL al fiecărui membru al staff-ului, redesenat la cererea
 * beneficiarului (07.08.2026): linia pe 1/3/6 LUNI ascundea exact ce contează — ritmul ZILNIC al
 * muncii de școală — și pe date reale arăta „plat, apoi un vârf". Reprezentarea nouă e un calendar
 * de intensitate (săptămâni × zile, à la contribution graph): fiecare pătrățel = o zi, culoarea =
 * câte acțiuni a făcut UTILIZATORUL în ziua aceea. Weekendurile golașe și vacanțele se văd singure,
 * fără nicio axă de explicat.
 *
 * Sursă ENTITY-BASED, ca înainte (deliberat FĂRĂ jurnalul de audit — acela amestecă omul cu
 * recalculele de sistem și nu acoperă GradeCorrection/Message):
 *   • Note      — note ACTIVE introduse de el (Grade.teacher_id, created_at);
 *   • Absențe   — absențe CONSEMNATE de el (Absence.teacher_id, created_at);
 *   • Corecții  — cerute (requested_by, created_at) + revizuite (reviewed_by, reviewed_at);
 *   • Motivări  — revizuite de el (reviewed_by, reviewed_at);
 *   • Mesaje    — trimise de el (sender_user_id, created_at).
 *
 * ⚠️ FUSUL: stocarea e UTC, ziua se judecă în fusul ȘCOLII ({@see SchoolCalendar}) — o notă pusă la
 * 21:30 UTC aparține zilei URMĂTOARE de catalog. De aceea bucketarea se face în PHP, pe timestamp
 * convertit, nu cu DATE() în SQL (care ar tăia pe UTC și e și dialect-dependent).
 *
 * Operare fără nimic ascuns: perioada = pastile inline (4/12 săptămâni, semestrul curent), nu un
 * meniu după pâlnie; categoriile = chips cu NUMĂRĂTORI, click = aprinde/stinge; celula își spune
 * defalcarea în tooltip. Doar categoriile cu activitate în fereastră primesc chip — un director
 * fără fișă de profesor nu mai vede „Note 0" ca zgomot.
 */
class ActivityMonitor extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.activity-pulse';

    /** Fereastra activă: 4 / 12 săptămâni sau semestrul curent. */
    public string $period = '12w';

    /**
     * Categoriile STINSE de utilizator (chips). Gol = tot ce are activitate e aprins — cu
     * numărătorile la vedere, „toate aprinse" e implicitul intuitiv; vechea logică pe rol exista
     * doar fiindcă filtrele stăteau ascunse după pâlnie.
     *
     * @var list<string>
     */
    public array $off = [];

    private const PERIODS = ['4w', '12w', 'sem'];

    private const CATEGORY_KEYS = ['grades', 'absences', 'corrections', 'motivations', 'messages'];

    /** Paletă ancorată în brand — folosită la chips și la defalcarea din tooltip. */
    private const COLORS = [
        'grades' => '#9bc31e',       // verde accent
        'absences' => '#e0a516',     // chihlimbar — absența e „galbenă" peste tot în catalog
        'corrections' => '#3d82b8',  // tentă de navy
        'motivations' => '#5f7a13',  // nuanță de verde (olive)
        'messages' => '#686867',     // gri de brand
    ];

    public static function canView(): bool
    {
        // Secțiune standard pentru TOT staff-ul: fiecare își vede propria activitate (personală).
        return auth('web')->check();
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, self::PERIODS, true) ? $period : '12w';
    }

    public function toggleCategory(string $key): void
    {
        if (! in_array($key, self::CATEGORY_KEYS, true)) {
            return;
        }

        $this->off = in_array($key, $this->off, true)
            ? array_values(array_diff($this->off, [$key]))
            : [...$this->off, $key];
    }

    /**
     * Întreaga stare a pulsului, gata de desen — API-ul public al widget-ului (testabil direct).
     *
     * @return array{
     *     weeks: list<list<array{iso: string, count: int, level: int, today: bool, future: bool, title: string}>>,
     *     month_marks: array<int, string>,
     *     weekday_labels: list<string>,
     *     kpi: array{today: int, week: int, total: int, peak: array{label: string, count: int}|null},
     *     cats: list<array{key: string, label: string, count: int, color: string, active: bool}>,
     *     period: string,
     *     period_options: array<string, string>,
     *     empty: bool,
     * }
     */
    public function pulse(): array
    {
        [$start, $end] = $this->window();

        $today = SchoolCalendar::localNow()->startOfDay();
        $counts = $this->dailyCounts($start, $end);

        // Chips: doar categoriile cu activitate în fereastră; active = neatinse de toggle.
        $cats = [];
        $activeKeys = [];

        foreach (self::CATEGORY_KEYS as $key) {
            $categoryTotal = array_sum(array_column($counts, $key));

            if ($categoryTotal === 0) {
                continue;
            }

            $active = ! in_array($key, $this->off, true);
            $cats[] = [
                'key' => $key,
                'label' => (string) __("panel.widgets.activity_monitor.series.$key"),
                'count' => $categoryTotal,
                'color' => self::COLORS[$key],
                'active' => $active,
            ];

            if ($active) {
                $activeKeys[] = $key;
            }
        }

        // Totalul pe zi = suma categoriilor APRINSE — ce e stins dispare și din culori, și din KPI.
        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($counts as $iso => $perCategory) {
            $daily[$iso] = array_sum(array_intersect_key($perCategory, array_flip($activeKeys)));
        }

        $max = $daily === [] ? 0 : max($daily);

        $weeks = [];
        $monthMarks = [];
        $cursor = $start->copy();
        $weekIndex = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            $week = [];

            // Eticheta lunii pe coloana în care începe (prima săptămână sau schimbarea lunii).
            if ($weekIndex === 0 || $cursor->day <= 7) {
                $monthMarks[$weekIndex] = ucfirst($this->localized($cursor)->isoFormat('MMM'));
            }

            foreach (range(0, 6) as $offset) {
                $day = $cursor->copy()->addDays($offset);
                $iso = $day->toDateString();
                $count = $daily[$iso] ?? 0;

                $week[] = [
                    'iso' => $iso,
                    'count' => $count,
                    'level' => $this->level($count, $max),
                    'today' => $day->isSameDay($today),
                    'future' => $day->greaterThan($today),
                    'title' => $this->cellTitle($day, $count, $counts[$iso] ?? [], $activeKeys),
                ];
            }

            $weeks[] = $week;
            $cursor->addWeek();
            $weekIndex++;
        }

        $weekStart = $today->copy()->startOfWeek();
        $todayIso = $today->toDateString();

        $peakIso = null;
        $peakCount = 0;

        foreach ($daily as $iso => $count) {
            if ($count > $peakCount) {
                [$peakIso, $peakCount] = [$iso, $count];
            }
        }

        $total = array_sum($daily);

        return [
            'weeks' => $weeks,
            'month_marks' => $monthMarks,
            'weekday_labels' => $this->weekdayLabels(),
            'kpi' => [
                'today' => $daily[$todayIso] ?? 0,
                'week' => array_sum(array_filter(
                    $daily,
                    fn (string $iso): bool => $iso >= $weekStart->toDateString() && $iso <= $todayIso,
                    ARRAY_FILTER_USE_KEY,
                )),
                'total' => $total,
                'peak' => $peakIso === null ? null : [
                    'label' => $this->localized(Carbon::parse($peakIso))->isoFormat('D MMM'),
                    'count' => $peakCount,
                ],
            ],
            'cats' => $cats,
            'period' => in_array($this->period, self::PERIODS, true) ? $this->period : '12w',
            'period_options' => $this->periodOptions(),
            'empty' => $cats === [],
        ];
    }

    /** Copie cu locale-ul aplicației aplicat — `locale()` întoarce static|string și rupe chaining-ul. */
    private function localized(Carbon $moment): Carbon
    {
        $copy = $moment->copy();
        $copy->locale(app()->getLocale());

        return $copy;
    }

    /**
     * Fereastra activă în fusul școlii: [luni-ul primei săptămâni, duminica săptămânii curente].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $end = $today->copy()->endOfWeek();

        if ($this->period === 'sem') {
            $termStart = SchoolCalendar::currentTerm()?->starts_on;

            if ($termStart !== null) {
                return [Carbon::parse($termStart->toDateString(), SchoolCalendar::TIMEZONE)->startOfWeek(), $end];
            }
        }

        $weeks = $this->period === '4w' ? 4 : 12;

        return [$today->copy()->subWeeks($weeks - 1)->startOfWeek(), $end];
    }

    /**
     * Acțiunile pe ZI (fusul școlii) × categorie, în fereastră — o interogare ușoară per sursă
     * (pluck de timestamps pe coloane indexate), bucketată în PHP ca să respecte fusul.
     *
     * @return array<string, array<string, int>>
     */
    private function dailyCounts(Carbon $start, Carbon $end): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return [];
        }

        $teacherId = $user->teacher?->getKey();
        $userId = (int) $user->getKey();

        // Granițele în UTC pentru interogare: fereastra e în fusul școlii.
        $utcStart = $start->copy()->timezone('UTC');
        $utcEnd = $end->copy()->endOfDay()->timezone('UTC');

        /** @var array<string, array<string, int>> $out */
        $out = [];

        $tally = function (iterable $moments, string $key) use (&$out): void {
            foreach ($moments as $moment) {
                $local = SchoolCalendar::local(Carbon::parse((string) $moment));

                if ($local === null) {
                    continue;
                }

                $iso = $local->toDateString();
                $out[$iso][$key] = ($out[$iso][$key] ?? 0) + 1;
            }
        };

        if ($teacherId !== null) {
            $tally(Grade::query()
                ->active()
                ->where('teacher_id', $teacherId)
                ->whereBetween('created_at', [$utcStart, $utcEnd])
                ->pluck('created_at'), 'grades');

            $tally(Absence::query()
                ->where('teacher_id', $teacherId)
                ->whereBetween('created_at', [$utcStart, $utcEnd])
                ->pluck('created_at'), 'absences');
        }

        $tally(GradeCorrection::query()
            ->where('requested_by_user_id', $userId)
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->pluck('created_at'), 'corrections');

        $tally(GradeCorrection::query()
            ->where('reviewed_by_user_id', $userId)
            ->whereBetween('reviewed_at', [$utcStart, $utcEnd])
            ->pluck('reviewed_at'), 'corrections');

        $tally(AbsenceMotivation::query()
            ->where('reviewed_by_user_id', $userId)
            ->whereBetween('reviewed_at', [$utcStart, $utcEnd])
            ->pluck('reviewed_at'), 'motivations');

        $tally(Message::query()
            ->where('sender_user_id', $userId)
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->pluck('created_at'), 'messages');

        return $out;
    }

    /**
     * Intensitatea 0–4 a unei zile, RELATIVĂ la vârful ferestrei — scala GitHub: nu contează cifra
     * absolută, ci „cât de plină e ziua față de zilele mele bune".
     */
    private function level(int $count, int $max): int
    {
        if ($count === 0 || $max === 0) {
            return 0;
        }

        if ($max <= 4) {
            return min(4, $count);
        }

        return min(4, 1 + (int) floor(3 * ($count - 1) / ($max - 1)));
    }

    /**
     * Tooltip-ul celulei: data + totalul + defalcarea pe categoriile aprinse (doar cele nenule).
     *
     * @param  array<string, int>  $perCategory
     * @param  list<string>  $activeKeys
     */
    private function cellTitle(Carbon $day, int $count, array $perCategory, array $activeKeys): string
    {
        $label = ucfirst($this->localized($day)->isoFormat('dd, D MMM'));

        if ($count === 0) {
            return $label.' — '.__('panel.widgets.activity_monitor.tooltip_none');
        }

        $parts = [];

        foreach ($activeKeys as $key) {
            $n = $perCategory[$key] ?? 0;

            if ($n > 0) {
                $parts[] = $n.' '.mb_strtolower((string) __("panel.widgets.activity_monitor.series.$key"));
            }
        }

        return $label.' — '.trans_choice('panel.widgets.activity_monitor.tooltip_actions', $count, ['count' => $count])
            .($parts === [] ? '' : ' ('.implode(', ', $parts).')');
    }

    /**
     * @return list<string>
     */
    private function weekdayLabels(): array
    {
        $monday = SchoolCalendar::localNow()->startOfWeek();
        $labels = [];

        foreach (range(0, 6) as $offset) {
            $labels[] = ucfirst($this->localized($monday->copy()->addDays($offset))->isoFormat('dd'));
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function periodOptions(): array
    {
        $options = [
            '4w' => (string) trans_choice('panel.widgets.activity_monitor.period_weeks', 4, ['count' => 4]),
            '12w' => (string) trans_choice('panel.widgets.activity_monitor.period_weeks', 12, ['count' => 12]),
        ];

        if (SchoolCalendar::currentTerm()?->starts_on !== null) {
            $options['sem'] = (string) __('panel.widgets.activity_monitor.period_term');
        }

        return $options;
    }
}
