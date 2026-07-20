<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Enums\HolidayType;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Support\SchoolCalendar;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Planificatorul zilelor libere: NU un tabel, ci anul școlar văzut ca CALENDAR — lunile
 * septembrie→august cu intervalele colorate pe categorii ({@see HolidayType}), plus cronologia
 * detaliată. Tabelul ascundea exact ce contează aici: unde cad zilele, cât țin vacanțele, ce
 * urmează. Filtrare pe an (pastile), categorie și căutare; scrierea rămâne a administratorului
 * operațional, restul rolurilor cu drept de citire văd aceeași imagine fără butoane de scriere.
 */
class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayResource::class;

    protected string $view = 'filament.configuration.holidays-planner';

    #[Url(as: 'an')]
    public ?int $yearParam = null;

    #[Url(as: 'tip')]
    public ?string $typeParam = null;

    #[Url(as: 'cauta')]
    public ?string $search = null;

    /** @var Collection<int, Holiday>|null */
    private ?Collection $holidaysCache = null;

    protected function getHeaderActions(): array
    {
        return [
            // Link către pagina generatorului, NU modal: pagina asta e HasTable cu view custom,
            // iar modalele acțiunilor de header ale paginilor HasTable se randează în view-ul
            // TABELULUI — care aici nu există (vezi LegalHolidaysGenerator).
            Action::make('legalHolidays')
                ->label(__('panel.holiday_planner.generator.action'))
                ->icon('heroicon-o-scale')
                ->color('gray')
                ->visible(fn (): bool => $this->canWrite())
                ->url(fn (): string => HolidayResource::getUrl('legal', array_filter(['an' => $this->activeYear()?->id]))),

            CreateAction::make()
                ->label(__('panel.holiday_planner.add'))
                ->icon('heroicon-o-plus'),
        ];
    }

    public function canWrite(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    /**
     * Culorile pe categorii trăiesc AICI, nu în enum: tema Filament compilează Tailwind doar din
     * globurile ei @source (app/Filament/**, resources/views/**) — clase scrise în app/Enums ar
     * rămâne necompilate (celule transparente; pățit la verificarea live). Paleta de BAZĂ Tailwind
     * (sky/green/amber), nu scala semantică Filament: aceea există doar ca variabile CSS
     * (--info-100), fără utilitare `bg-info-100`.
     */
    public static function cellClassesFor(HolidayType $type): string
    {
        return match ($type) {
            HolidayType::LegalHoliday => 'bg-sky-100 text-sky-800 dark:bg-sky-500/25 dark:text-sky-200',
            HolidayType::Vacation => 'bg-green-100 text-green-800 dark:bg-green-500/25 dark:text-green-200',
            HolidayType::InstitutionalDay => 'bg-amber-100 text-amber-800 dark:bg-amber-500/25 dark:text-amber-200',
            HolidayType::Other => 'bg-gray-200 text-gray-700 dark:bg-white/15 dark:text-gray-200',
        };
    }

    /** Punctul de legendă / cronologie — aceleași familii de nuanțe ca celulele. */
    public static function dotClassesFor(HolidayType $type): string
    {
        return match ($type) {
            HolidayType::LegalHoliday => 'bg-sky-500',
            HolidayType::Vacation => 'bg-green-500',
            HolidayType::InstitutionalDay => 'bg-amber-500',
            HolidayType::Other => 'bg-gray-400',
        };
    }

    // ── Filtre (an / categorie / căutare) ──────────────────────────────────────────────

    /** @return list<array{id: int, label: string, count: int}> */
    public function yearPills(): array
    {
        $pills = AcademicYear::query()
            ->orderByDesc('starts_on')
            ->orderByDesc('name')
            ->get()
            ->map(function (AcademicYear $year): array {
                [$from, $to] = $this->spanFor($year);

                return [
                    'id' => (int) $year->id,
                    'label' => $year->name,
                    'count' => Holiday::query()->overlappingSpan($from, $to)->count(),
                ];
            })
            ->all();

        return array_values($pills);
    }

    public function activeYear(): ?AcademicYear
    {
        if ($this->yearParam !== null) {
            $selected = AcademicYear::query()->find($this->yearParam);

            if ($selected !== null) {
                return $selected;
            }
        }

        return SchoolCalendar::currentYear()
            ?? AcademicYear::query()->orderByDesc('starts_on')->orderByDesc('name')->first();
    }

    public function openYear(int $yearId): void
    {
        $this->yearParam = $yearId;
        $this->holidaysCache = null;
    }

    public function openType(?string $type): void
    {
        $this->typeParam = $type;
        $this->holidaysCache = null;
    }

    public function updatedSearch(): void
    {
        $this->holidaysCache = null;
    }

    public function activeType(): ?HolidayType
    {
        return $this->typeParam !== null ? HolidayType::tryFrom($this->typeParam) : null;
    }

    /**
     * Intervalul anului școlar activ: datele configurate sau, în lipsa lor, 1 septembrie –
     * 31 august deduse din denumire („2025-2026").
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function activeSpan(): array
    {
        $year = $this->activeYear();

        if ($year === null) {
            $now = SchoolCalendar::localNow();
            $septemberYear = $now->month >= 9 ? $now->year : $now->year - 1;

            return [Carbon::create($septemberYear, 9, 1), Carbon::create($septemberYear + 1, 8, 31)];
        }

        return $this->spanFor($year);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function spanFor(AcademicYear $year): array
    {
        return SchoolCalendar::yearSpan($year);
    }

    // ── Datele planificatorului ────────────────────────────────────────────────────────

    /**
     * Zilele libere ale anului activ, cu filtrele aplicate (categorie + căutare), cronologic.
     *
     * @return Collection<int, Holiday>
     */
    public function holidays(): Collection
    {
        if ($this->holidaysCache !== null) {
            return $this->holidaysCache;
        }

        [$from, $to] = $this->activeSpan();

        $query = Holiday::query()
            ->overlappingSpan($from, $to)
            ->orderBy('starts_on');

        if ($this->activeType() !== null) {
            $query->where('type', $this->activeType()->value);
        }

        $search = trim((string) $this->search);

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $this->holidaysCache = $query->get();
    }

    /**
     * Pastilele de categorie, cu numărul de ZILE (nu de înregistrări) — măsura onestă:
     * o vacanță de 3 săptămâni cântărește altfel decât o sărbătoare de o zi.
     *
     * @return list<array{value: string|null, label: string, days: int, dot: string|null, active: bool}>
     */
    public function typePills(): array
    {
        [$from, $to] = $this->activeSpan();

        $all = Holiday::query()->overlappingSpan($from, $to)->orderBy('starts_on')->get();

        $daysFor = function (Collection $set) use ($from, $to): int {
            $dates = [];

            foreach ($set as $holiday) {
                // Clamp pe interval prin COPII — max()/min() pe Carbon ar putea întoarce chiar
                // $from/$to, pe care startOfDay() le-ar muta pentru toți apelanții următori.
                $cursor = Carbon::parse($holiday->starts_on)->startOfDay();
                $end = $holiday->effectiveEndsOn()->startOfDay();

                if ($cursor->lt($from)) {
                    $cursor = $from->copy()->startOfDay();
                }

                if ($end->gt($to)) {
                    $end = $to->copy()->startOfDay();
                }

                while ($cursor->lte($end)) {
                    $dates[$cursor->toDateString()] = true;
                    $cursor = $cursor->addDay();
                }
            }

            return count($dates);
        };

        $pills = [[
            'value' => null,
            'label' => __('panel.holiday_planner.all'),
            'days' => $daysFor($all),
            'dot' => null,
            'active' => $this->activeType() === null,
        ]];

        foreach (HolidayType::cases() as $type) {
            $subset = $all->filter(fn (Holiday $holiday): bool => $holiday->type === $type);

            if ($subset->isEmpty() && $this->activeType() !== $type) {
                continue;
            }

            $pills[] = [
                'value' => $type->value,
                'label' => $type->label(),
                'days' => $daysFor($subset),
                'dot' => self::dotClassesFor($type),
                'active' => $this->activeType() === $type,
            ];
        }

        return $pills;
    }

    /**
     * Următoarea zi liberă de la AZI (în fusul școlii), indiferent de filtre — afordanța „acum".
     *
     * @return array{holiday: Holiday, in_days: int, ongoing: bool}|null
     */
    public function nextFreeDay(): ?array
    {
        $today = SchoolCalendar::localNow()->startOfDay();

        $next = Holiday::query()
            ->whereRaw('DATE(COALESCE(ends_on, starts_on)) >= ?', [$today->toDateString()])
            ->orderBy('starts_on')
            ->first();

        if ($next === null) {
            return null;
        }

        $start = Carbon::parse($next->starts_on)->startOfDay();

        return [
            'holiday' => $next,
            'in_days' => $start->gt($today) ? (int) $today->diffInDays($start) : 0,
            'ongoing' => $start->lte($today),
        ];
    }

    /**
     * Lunile anului școlar, fiecare cu săptămânile ei (luni→duminică) și zilele marcate.
     *
     * @return list<array{label: string, free_days: int, weeks: list<list<array{day: int, in_month: bool, weekend: bool, today: bool, holiday: array{id: int, name: string, cell: string, is_start: bool, is_end: bool, edit_url: string|null}|null}>>}>
     */
    public function months(): array
    {
        [$from, $to] = $this->activeSpan();

        $byDate = [];

        foreach ($this->holidays() as $holiday) {
            $cursor = Carbon::parse($holiday->starts_on)->startOfDay();
            $end = $holiday->effectiveEndsOn()->startOfDay();

            while ($cursor->lte($end)) {
                // Prima înregistrare pe o dată câștigă; suprapunerile sunt improbabile și oricum
                // vizibile în cronologie.
                $byDate[$cursor->toDateString()] ??= [
                    'id' => (int) $holiday->id,
                    'name' => $holiday->name,
                    'cell' => self::cellClassesFor($holiday->type),
                    'is_start' => $cursor->equalTo(Carbon::parse($holiday->starts_on)->startOfDay()),
                    'is_end' => $cursor->equalTo($end),
                    'edit_url' => $this->canWrite()
                        ? HolidayResource::getUrl('edit', ['record' => $holiday])
                        : null,
                ];

                $cursor = $cursor->addDay();
            }
        }

        $today = SchoolCalendar::localNow()->toDateString();
        $months = [];
        $monthCursor = $from->copy()->startOfMonth();

        while ($monthCursor->lte($to)) {
            $weeks = [];
            $week = [];
            $freeDays = 0;

            $dayCursor = $monthCursor->copy()->startOfWeek(Carbon::MONDAY);
            $monthEnd = $monthCursor->copy()->endOfMonth();
            $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

            while ($dayCursor->lte($gridEnd)) {
                $inMonth = $dayCursor->month === $monthCursor->month;
                $holiday = $inMonth ? ($byDate[$dayCursor->toDateString()] ?? null) : null;

                if ($holiday !== null) {
                    $freeDays++;
                }

                $week[] = [
                    'day' => (int) $dayCursor->day,
                    'in_month' => $inMonth,
                    'weekend' => $dayCursor->isWeekend(),
                    'today' => $inMonth && $dayCursor->toDateString() === $today,
                    'holiday' => $holiday,
                ];

                if (count($week) === 7) {
                    $weeks[] = $week;
                    $week = [];
                }

                $dayCursor = $dayCursor->addDay();
            }

            $months[] = [
                'label' => ucfirst($monthCursor->translatedFormat('F Y')),
                'free_days' => $freeDays,
                'weeks' => $weeks,
            ];

            $monthCursor = $monthCursor->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * Cronologia: fiecare zi liberă drept card, cu luna-ancoră, durata și starea față de azi.
     *
     * @return list<array{holiday: Holiday, month: string, range: string, days: int, dot: string, past: bool, current: bool, edit_url: string|null}>
     */
    public function timeline(): array
    {
        $today = SchoolCalendar::localNow()->startOfDay();

        $entries = $this->holidays()
            ->map(function (Holiday $holiday) use ($today): array {
                $start = Carbon::parse($holiday->starts_on)->startOfDay();
                $end = $holiday->effectiveEndsOn()->startOfDay();

                return [
                    'holiday' => $holiday,
                    'month' => ucfirst($start->translatedFormat('F Y')),
                    'range' => $this->formatRange($start->toDateString(), $holiday->ends_on?->toDateString()),
                    'days' => $holiday->lengthInDays(),
                    'dot' => self::dotClassesFor($holiday->type),
                    'past' => $end->lt($today),
                    'current' => $start->lte($today) && $end->gte($today),
                    'edit_url' => $this->canWrite()
                        ? HolidayResource::getUrl('edit', ['record' => $holiday])
                        : null,
                ];
            })
            ->all();

        return array_values($entries);
    }

    /** @return list<string> */
    public function weekdayInitials(): array
    {
        return [
            __('panel.holiday_planner.weekdays.mon'),
            __('panel.holiday_planner.weekdays.tue'),
            __('panel.holiday_planner.weekdays.wed'),
            __('panel.holiday_planner.weekdays.thu'),
            __('panel.holiday_planner.weekdays.fri'),
            __('panel.holiday_planner.weekdays.sat'),
            __('panel.holiday_planner.weekdays.sun'),
        ];
    }

    private function formatRange(string $start, ?string $end): string
    {
        $startDate = Carbon::parse($start);

        if ($end === null || $end === $start) {
            return $startDate->translatedFormat('d.m.Y');
        }

        return $startDate->translatedFormat('d.m.Y').' – '.Carbon::parse($end)->translatedFormat('d.m.Y');
    }
}
