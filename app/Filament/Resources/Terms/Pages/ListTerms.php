<?php

namespace App\Filament\Resources\Terms\Pages;

use App\Actions\SyncCurrentTermFlag;
use App\Enums\HolidayType;
use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\Terms\TermResource;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\CorigentaSession;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\SemesterValidation;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * „Semestre" = AXA anului academic (2026-07-21), nu un tabel: anul văzut ca o cronologie
 * septembrie→august — benzile semestrelor, vacanțele/sărbătorile, sesiunile de corigență și
 * reperul AZI — plus câte un card bogat per semestru (stare, progres, durata în săptămâni și
 * zile de școală, datele ancorate: note/absențe/medii/validări) și SEMNALELE de integritate
 * (semestru curent nesincronizat, semestre fără interval, evaluări datate în afara semestrului).
 * Perioadele se definesc aici; anul își ține ciclul de viață în „Ani școlari" (hub).
 */
class ListTerms extends ListRecords
{
    use HasYearPillsTable;

    protected static string $resource = TermResource::class;

    protected string $view = 'filament.configuration.terms-year-axis';

    /** @var array{start: Carbon, end: Carbon, months: list<array{label: string, left: float}>, terms: list<array{left: float, width: float, name: string, short: string, current: bool}>, holidays: list<array{left: float, width: float, class: string, title: string}>, sessions: list<array{left: float, width: float, title: string}>, today: float|null}|null */
    private ?array $axisMemo = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $cardsMemo = null;

    /** @var Collection<int, Term>|null */
    private ?Collection $termsMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            // Fără modal pe acțiunea de sincronizare: pagina e HasTable cu view custom, iar
            // modalele acțiunilor de header s-ar randa în view-ul TABELULUI, care aici nu există
            // (gotcha-ul planificatorului de zile libere). Acțiunea e idempotentă și derivată
            // strict din intervalele de date — sigură fără confirmare.
            Action::make('syncCurrentTerm')
                ->label(__('panel.terms_axis.sync.label'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->canWrite() && $this->isCurrentStale())
                ->action(function (SyncCurrentTermFlag $sync): void {
                    $current = $sync->run();

                    $this->resetMemos();

                    Notification::make()
                        ->success()
                        ->title(__('panel.terms_axis.sync.done'))
                        ->body($current !== null
                            ? __('panel.terms_axis.sync.result', ['term' => $current->name])
                            : __('panel.terms_axis.sync.nothing'))
                        ->send();
                }),

            CreateAction::make()
                ->label(__('panel.terms_axis.add'))
                ->icon('heroicon-o-plus')
                // Anul activ din pastile călătorește spre formular (default-ul selectului).
                ->url(fn (): string => TermResource::getUrl('create', array_filter(['an' => $this->activeYearId()]))),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.terms_hint');
    }

    public function canWrite(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public function activeYear(): ?AcademicYear
    {
        $id = $this->activeYearId();

        return $id === null ? null : AcademicYear::query()->find($id);
    }

    public function isYearClosed(): bool
    {
        return $this->activeYear()?->isClosed() ?? false;
    }

    /**
     * Flag-ul `is_current` a rămas în urma calendarului (scheduler-ul nocturn n-a rulat încă /
     * a fost oprit): semestrul care AR TREBUI să fie curent după intervale diferă de cel marcat.
     */
    public function isCurrentStale(): bool
    {
        $expected = app(SyncCurrentTermFlag::class)->determine();

        return $expected !== null && (int) $expected->id !== SchoolCalendar::currentTermId();
    }

    /**
     * AXA anului: benzile semestrelor, vacanțele, sesiunile de corigență și reperul AZI,
     * toate poziționate procentual pe intervalul calendaristic al anului activ.
     *
     * @return array{start: Carbon, end: Carbon, months: list<array{label: string, left: float}>, terms: list<array{left: float, width: float, name: string, short: string, current: bool}>, holidays: list<array{left: float, width: float, class: string, title: string}>, sessions: list<array{left: float, width: float, title: string}>, today: float|null}|null
     */
    public function axis(): ?array
    {
        if ($this->axisMemo !== null) {
            return $this->axisMemo;
        }

        $year = $this->activeYear();

        if ($year === null) {
            return null;
        }

        [$start, $end] = SchoolCalendar::yearSpan($year);
        $spanDays = max(1, (int) $start->diffInDays($end));

        // CarbonInterface: cast-urile de model produc CarbonImmutable (Date::use), iar span-ul
        // anului vine ca Carbon mutabil — procentul le acceptă pe amândouă.
        $pct = static function (CarbonInterface $date) use ($start, $spanDays): float {
            $days = $start->diffInDays($date, false);

            return round(max(0.0, min(100.0, $days / $spanDays * 100)), 2);
        };

        $months = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            if ($cursor->gte($start)) {
                $months[] = [
                    // translatedFormat urmează limba de interfață a utilizatorului curent.
                    'label' => $cursor->translatedFormat('M'),
                    'left' => $pct($cursor->copy()),
                ];
            }
            $cursor->addMonth();
        }

        $terms = [];
        foreach ($this->yearTerms() as $term) {
            $startsOn = $term->starts_on;
            $endsOn = $term->ends_on;

            if ($startsOn === null || $endsOn === null) {
                continue;
            }

            $terms[] = [
                'left' => $pct($startsOn->copy()),
                'width' => max(1.5, $pct($endsOn->copy()) - $pct($startsOn->copy())),
                'name' => (string) $term->name,
                'short' => 'S'.$term->number,
                'current' => (bool) $term->is_current,
            ];
        }

        $holidays = array_values(Holiday::query()
            ->overlappingSpan($start, $end)
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'left' => $pct(Carbon::parse($holiday->starts_on)),
                'width' => max(0.5, $pct($holiday->effectiveEndsOn()) - $pct(Carbon::parse($holiday->starts_on))),
                'class' => self::holidayBandClasses($holiday->type),
                'title' => (string) ($holiday->name.' · '.Carbon::parse($holiday->starts_on)->format('d.m').'–'.$holiday->effectiveEndsOn()->format('d.m')),
            ])
            ->all());

        $sessions = array_values(CorigentaSession::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->get()
            ->map(fn (CorigentaSession $session): array => [
                'left' => $pct($session->starts_on->copy()),
                'width' => max(0.5, $pct($session->ends_on->copy()) - $pct($session->starts_on->copy())),
                'title' => (string) __('panel.terms_axis.session_title', [
                    'season' => $session->season->getLabel(),
                    'status' => $session->status->getLabel(),
                ]),
            ])
            ->all());

        $today = Carbon::today();

        return $this->axisMemo = [
            'start' => $start,
            'end' => $end,
            'months' => $months,
            'terms' => $terms,
            'holidays' => $holidays,
            'sessions' => $sessions,
            'today' => $today->between($start, $end) ? $pct($today) : null,
        ];
    }

    /**
     * Cardurile semestrelor anului activ: stare + interval + durată (săptămâni / zile de școală,
     * fără weekenduri și zile libere) + progresul semestrului curent + datele ancorate de el.
     *
     * @return list<array<string, mixed>>
     */
    public function termCards(): array
    {
        if ($this->cardsMemo !== null) {
            return $this->cardsMemo;
        }

        $terms = $this->yearTerms();

        /** @var list<int> $ids */
        $ids = array_values($terms->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $grades = $this->countsFor(Grade::query(), $ids);
        $absences = $this->countsFor(Absence::query(), $ids);
        $averages = $this->countsFor(TermAverage::query(), $ids);
        $validations = $this->countsFor(SemesterValidation::query(), $ids);

        $today = Carbon::today();
        $user = auth('web')->user();

        $cards = array_values($terms
            ->map(function (Term $term) use ($grades, $absences, $averages, $validations, $today, $user): array {
                $startsOn = $term->starts_on;
                $endsOn = $term->ends_on;

                $interval = null;
                $totalDays = null;
                $schoolDays = null;
                $progress = null;
                $status = 'undated';

                if ($startsOn !== null && $endsOn !== null) {
                    $interval = $startsOn->format('d.m.Y').' – '.$endsOn->format('d.m.Y');
                    $totalDays = (int) $startsOn->diffInDays($endsOn) + 1;
                    $schoolDays = $this->schoolDaysBetween($startsOn->copy(), $endsOn->copy());

                    $status = match (true) {
                        $term->is_current => 'current',
                        $endsOn->lt($today) => 'past',
                        $startsOn->gt($today) => 'future',
                        default => 'idle',
                    };

                    if ($term->is_current && $today->between($startsOn, $endsOn)) {
                        $elapsed = (int) $startsOn->diffInDays($today) + 1;
                        $progress = [
                            'percent' => (int) round($elapsed / max(1, $totalDays) * 100),
                            'week' => (int) ceil($elapsed / 7),
                            'weeks' => (int) ceil($totalDays / 7),
                        ];
                    }
                }

                return [
                    'id' => (int) $term->id,
                    'name' => (string) $term->name,
                    'number' => (int) $term->number,
                    'status' => $status,
                    'interval' => $interval,
                    'weeks' => $totalDays !== null ? (int) ceil($totalDays / 7) : null,
                    'school_days' => $schoolDays,
                    'progress' => $progress,
                    'counts' => [
                        'grades' => (int) ($grades->get($term->id) ?? 0),
                        'absences' => (int) ($absences->get($term->id) ?? 0),
                        'averages' => (int) ($averages->get($term->id) ?? 0),
                        'validations' => (int) ($validations->get($term->id) ?? 0),
                    ],
                    'drift' => $interval !== null ? $this->driftFor($term) : 0,
                    'edit_url' => $user instanceof User && $user->can('update', $term)
                        ? TermResource::getUrl('edit', ['record' => $term])
                        : null,
                ];
            })
            ->all());

        return $this->cardsMemo = $cards;
    }

    /**
     * Semnalele de integritate ale structurii anului activ — vizibile ÎNAINTE ca cineva să se
     * lovească de efect (note căzute în alt semestru, filtre goale, flag stale).
     *
     * @return list<array{level: string, text: string}>
     */
    public function integrity(): array
    {
        $signals = [];

        if ($this->isYearClosed()) {
            $signals[] = [
                'level' => 'info',
                'text' => (string) __('panel.terms_axis.integrity.year_closed'),
            ];
        }

        if ($this->isCurrentStale()) {
            $signals[] = [
                'level' => 'warning',
                'text' => (string) __($this->canWrite()
                    ? 'panel.terms_axis.integrity.stale_current'
                    : 'panel.terms_axis.integrity.stale_current_readonly'),
            ];
        }

        $undated = $this->yearTerms()
            ->filter(fn (Term $term): bool => $term->starts_on === null || $term->ends_on === null);

        if ($undated->isNotEmpty()) {
            $signals[] = [
                'level' => 'danger',
                'text' => (string) trans_choice('panel.terms_axis.integrity.undated', $undated->count(), [
                    'count' => $undated->count(),
                    'names' => $undated->pluck('name')->implode(', '),
                ]),
            ];
        }

        $drift = array_sum(array_column($this->termCards(), 'drift'));

        if ($drift > 0) {
            $signals[] = [
                'level' => 'warning',
                'text' => (string) trans_choice('panel.terms_axis.integrity.drift', $drift, ['count' => $drift]),
            ];
        }

        return $signals;
    }

    /**
     * Clasele benzilor de vacanță/sărbătoare pe axă. Trăiesc AICI, nu în enum: tema Filament
     * compilează Tailwind doar din globurile @source (app/Filament/**, resources/views/**) —
     * vezi gotcha-ul planificatorului de zile libere. Paleta de BAZĂ Tailwind, nu scala
     * semantică Filament (aceea există doar ca variabile CSS).
     */
    public static function holidayBandClasses(HolidayType $type): string
    {
        return match ($type) {
            HolidayType::LegalHoliday => 'bg-sky-400 dark:bg-sky-500',
            HolidayType::Vacation => 'bg-green-400 dark:bg-green-500',
            HolidayType::InstitutionalDay => 'bg-amber-400 dark:bg-amber-500',
            HolidayType::Other => 'bg-gray-400 dark:bg-gray-500',
        };
    }

    protected function yearRecordCounts(): Collection
    {
        return Term::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }

    protected function constrainToYear(Builder $query, int $yearId): void
    {
        $query->where('academic_year_id', $yearId);
    }

    /** @return Collection<int, Term> */
    private function yearTerms(): Collection
    {
        if ($this->termsMemo !== null) {
            return $this->termsMemo;
        }

        $yearId = $this->activeYearId();

        return $this->termsMemo = $yearId === null
            ? new Collection
            : Term::query()
                ->where('academic_year_id', $yearId)
                ->orderBy('number')
                ->get();
    }

    /**
     * Numărătoare per semestru, o singură interogare grupată per tabel.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<int>  $ids
     * @return Collection<int|string, int>
     */
    private function countsFor(Builder $query, array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return $query
            ->toBase()
            ->selectRaw('term_id, COUNT(*) AS aggregate')
            ->whereIn('term_id', $ids)
            ->groupBy('term_id')
            ->pluck('aggregate', 'term_id')
            ->map(fn ($count): int => (int) $count);
    }

    /** Zile de ȘCOALĂ în interval: fără weekenduri și fără zilele libere planificate. */
    private function schoolDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $days = 0;
        // Cursor MUTABIL indiferent de clasa de intrare: pe CarbonImmutable, addDay() întoarce
        // o instanță nouă și bucla while ar rămâne pe loc.
        $cursor = Carbon::parse($from->toDateString());
        // Plasă de siguranță pe intervale aberante (ani întregi introduși greșit): numărătoarea
        // rămâne corectă pentru orice semestru real, dar nu iterăm nemărginit.
        $guard = 0;

        while ($cursor->lte($to) && $guard < 400) {
            if (! Holidays::isNonWorkingDay($cursor)) {
                $days++;
            }
            $cursor->addDay();
            $guard++;
        }

        return $days;
    }

    /**
     * Evaluări rămase datate ÎN AFARA intervalului propriului semestru (după o mutare de granițe
     * pe date vechi sau un import cu date atipice). Realinierea automată le mută la următoarea
     * salvare a intervalelor; până atunci, semnalul le face vizibile.
     */
    private function driftFor(Term $term): int
    {
        $outside = fn (Builder $query, string $column): int => (int) $query
            ->toBase()
            ->where('term_id', $term->id)
            ->where(fn ($inner) => $inner
                ->whereDate($column, '<', $term->starts_on)
                ->orWhereDate($column, '>', $term->ends_on))
            ->count();

        return $outside(Grade::query(), 'graded_on') + $outside(Absence::query(), 'occurred_on');
    }

    private function resetMemos(): void
    {
        $this->axisMemo = null;
        $this->cardsMemo = null;
        $this->termsMemo = null;
    }
}
