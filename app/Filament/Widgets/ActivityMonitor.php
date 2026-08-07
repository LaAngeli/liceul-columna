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
 * beneficiarului (07.08.2026, v2 după feedback-ul pe heatmap: „calendar restrâns, spațiu gol, nu se
 * încadrează în design"). Reprezentarea: BARE STIVUITE pe TOATĂ lățimea cardului — o bară = o zi
 * (fereastra de 4 săptămâni) sau o săptămână (12 săptămâni / semestrul), înălțimea = câte acțiuni,
 * segmentele = categoriile, în culorile chips-urilor de dedesubt. Barele întind flex pe orice
 * lățime, deci cardul nu mai are pustiu; secțiunea e un `x-filament::section` nativ, ca vecinii.
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
 * meniu după pâlnie; categoriile = chips cu NUMĂRĂTORI, click = aprinde/stinge; bara își spune
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

    /** Paletă ancorată în brand — segmentele barelor și punctele chips-urilor. */
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
     * `bars` e gata calculat în PROCENTE pe server (morph-safe, fără JS de măsurat): înălțimea
     * barei = totalul zilei/săptămânii raportat la vârful ferestrei; segmentele — pe categorie.
     *
     * @return array{
     *     bars: list<array{iso: string, label: string, month_mark: string|null, title: string, total: int, today: bool, future: bool, weekend: bool, segments: list<array{key: string, color: string, height: float}>}>,
     *     granularity: string,
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

        // O bară pe ZI la fereastra scurtă (ritmul se citește literal), pe SĂPTĂMÂNĂ la cele
        // lungi — 12+ bare late umplu lățimea, 84 de bare zilnice ar redeveni ilizibile.
        $granularity = $this->period === '4w' ? 'day' : 'week';

        $buckets = $this->buckets($granularity, $start, $end, $counts, $activeKeys);

        $max = 0;

        foreach ($buckets as $bucket) {
            $max = max($max, $bucket['total']);
        }

        $bars = [];
        $previousMonth = null;

        foreach ($buckets as $bucket) {
            $anchor = Carbon::parse($bucket['iso'], SchoolCalendar::TIMEZONE);
            $month = ucfirst($this->localized($anchor)->isoFormat('MMM'));

            $segments = [];

            foreach ($activeKeys as $key) {
                $n = $bucket['per_category'][$key] ?? 0;

                if ($n > 0 && $max > 0) {
                    $segments[] = [
                        'key' => $key,
                        'color' => self::COLORS[$key],
                        'height' => round(100 * $n / $max, 2),
                    ];
                }
            }

            $bars[] = [
                'iso' => $bucket['iso'],
                'label' => $bucket['label'],
                'month_mark' => $month !== $previousMonth ? $month : null,
                'title' => $bucket['title'],
                'total' => $bucket['total'],
                'today' => $bucket['today'],
                'future' => $bucket['future'],
                'weekend' => $bucket['weekend'],
                'segments' => $segments,
            ];

            $previousMonth = $month;
        }

        $weekStart = $today->copy()->startOfWeek();
        $todayIso = $today->toDateString();

        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($counts as $iso => $perCategory) {
            $daily[$iso] = array_sum(array_intersect_key($perCategory, array_flip($activeKeys)));
        }

        $peakIso = null;
        $peakCount = 0;

        foreach ($daily as $iso => $count) {
            if ($count > $peakCount) {
                [$peakIso, $peakCount] = [$iso, $count];
            }
        }

        return [
            'bars' => $bars,
            'granularity' => $granularity,
            'kpi' => [
                'today' => $daily[$todayIso] ?? 0,
                'week' => array_sum(array_filter(
                    $daily,
                    fn (string $iso): bool => $iso >= $weekStart->toDateString() && $iso <= $todayIso,
                    ARRAY_FILTER_USE_KEY,
                )),
                'total' => array_sum($daily),
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

    /**
     * Găleata barelor: pe zi sau pe săptămână, cu totalul categoriilor APRINSE, eticheta scurtă și
     * tooltip-ul complet. Viitorul (restul săptămânii curente) rămâne în axă — bara e goală și
     * marcată, ca ritmul să nu pară că se termină brusc azi.
     *
     * @param  array<string, array<string, int>>  $counts
     * @param  list<string>  $activeKeys
     * @return list<array{iso: string, label: string, title: string, total: int, today: bool, future: bool, weekend: bool, per_category: array<string, int>}>
     */
    private function buckets(string $granularity, Carbon $start, Carbon $end, array $counts, array $activeKeys): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $out = [];

        if ($granularity === 'day') {
            $cursor = $start->copy();

            while ($cursor->lessThanOrEqualTo($end)) {
                $iso = $cursor->toDateString();
                $perCategory = array_intersect_key($counts[$iso] ?? [], array_flip($activeKeys));
                $total = array_sum($perCategory);

                $out[] = [
                    'iso' => $iso,
                    'label' => (string) $cursor->day,
                    'title' => $this->dayTitle($cursor, $total, $perCategory),
                    'total' => $total,
                    'today' => $cursor->isSameDay($today),
                    'future' => $cursor->greaterThan($today),
                    'weekend' => $cursor->isWeekend(),
                    'per_category' => $perCategory,
                ];

                $cursor->addDay();
            }

            return $out;
        }

        $cursor = $start->copy()->startOfWeek();

        while ($cursor->lessThanOrEqualTo($end)) {
            $weekEnd = $cursor->copy()->endOfWeek()->startOfDay();

            /** @var array<string, int> $perCategory */
            $perCategory = [];

            foreach (range(0, 6) as $offset) {
                $dayIso = $cursor->copy()->addDays($offset)->toDateString();

                foreach ($counts[$dayIso] ?? [] as $key => $n) {
                    if (in_array($key, $activeKeys, true)) {
                        $perCategory[$key] = ($perCategory[$key] ?? 0) + $n;
                    }
                }
            }

            $total = array_sum($perCategory);

            $out[] = [
                'iso' => $cursor->toDateString(),
                'label' => $this->localized($cursor)->isoFormat('D MMM'),
                'title' => $this->weekTitle($cursor, $weekEnd, $total, $perCategory),
                'total' => $total,
                'today' => $today->betweenIncluded($cursor, $weekEnd->copy()->endOfDay()),
                'future' => $cursor->greaterThan($today),
                'weekend' => false,
                'per_category' => $perCategory,
            ];

            $cursor->addWeek();
        }

        return $out;
    }

    /**
     * Fereastra activă în fusul școlii: [luni-ul primei săptămâni, duminica săptămânii curente].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();
        $end = $today->copy()->endOfWeek()->startOfDay();

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

            // `active()`, ca la note pe rândul de deasupra (07.08.2026 — absențele au acum
            // anulare): o consemnare desfăcută nu mai e activitate, iar pulsul ar fi arătat o zi
            // mai plină decât a fost.
            $tally(Absence::query()
                ->active()
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

    /** Copie cu locale-ul aplicației aplicat — `locale()` întoarce static|string și rupe chaining-ul. */
    private function localized(Carbon $moment): Carbon
    {
        $copy = $moment->copy();
        $copy->locale(app()->getLocale());

        return $copy;
    }

    /**
     * Tooltip-ul unei zile: data + totalul + defalcarea pe categoriile aprinse (doar cele nenule).
     *
     * @param  array<string, int>  $perCategory
     */
    private function dayTitle(Carbon $day, int $total, array $perCategory): string
    {
        return ucfirst($this->localized($day)->isoFormat('dd, D MMM'))
            .' — '.$this->tally($total, $perCategory);
    }

    /**
     * @param  array<string, int>  $perCategory
     */
    private function weekTitle(Carbon $start, Carbon $end, int $total, array $perCategory): string
    {
        return __('panel.widgets.activity_monitor.week_prefix', [
            'from' => $this->localized($start)->isoFormat('D MMM'),
            'to' => $this->localized($end)->isoFormat('D MMM'),
        ]).' — '.$this->tally($total, $perCategory);
    }

    /**
     * @param  array<string, int>  $perCategory
     */
    private function tally(int $total, array $perCategory): string
    {
        if ($total === 0) {
            return (string) __('panel.widgets.activity_monitor.tooltip_none');
        }

        $parts = [];

        foreach (self::CATEGORY_KEYS as $key) {
            $n = $perCategory[$key] ?? 0;

            if ($n > 0) {
                $parts[] = $n.' '.mb_strtolower((string) __("panel.widgets.activity_monitor.series.$key"));
            }
        }

        return trans_choice('panel.widgets.activity_monitor.tooltip_actions', $total, ['count' => $total])
            .($parts === [] ? '' : ' ('.implode(', ', $parts).')');
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
