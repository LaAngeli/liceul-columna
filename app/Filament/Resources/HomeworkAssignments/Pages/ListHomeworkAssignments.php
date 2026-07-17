<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Pagina „Teme" folosește navigatorul drill-down, ADAPTAT modelului temelor: tema țintește o
 * treaptă + literă (nu un school_class_id) și nu are semestru — deci dimensiunile sunt Clase /
 * Discipline / Profesori (fără „Perioade"), iar constrângerea de clasă se traduce în
 * (grade_level, section), incluzând temele date pe TOATĂ treapta (litera goală).
 *
 * TIMPUL e a doua axă (cerința beneficiarului 2026-07-18): în context, o bară temporală
 * comută între Toate / Zi / Săptămână / Lună, cu navigare ◀ ▶ pe perioadă și revenire la azi.
 * Filtrarea se face pe DATA EFECTIVĂ a temei (termen, cu fallback pe atribuire la legacy) —
 * starea trăiește în URL (?mod=, ?ref=) și e VALIDATĂ la citire.
 */
class ListHomeworkAssignments extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator {
        applyCatalogContext as baseApplyCatalogContext;
    }

    protected static string $resource = HomeworkAssignmentResource::class;

    protected string $view = 'filament.catalog.homework-navigator';

    /** Modul temporal activ: zi / saptamana / luna; null = toate temele contextului. */
    #[Url(as: 'mod', except: null)]
    public ?string $timeMode = null;

    /** Data de referință a perioadei (Y-m-d); null = azi. */
    #[Url(as: 'ref', except: null)]
    public ?string $timeRef = null;

    private const TIME_MODES = ['zi', 'saptamana', 'luna'];

    /** Modul temporal VALIDAT — URL-ul nu se ia de bun. */
    public function timeMode(): ?string
    {
        return in_array($this->timeMode, self::TIME_MODES, true) ? $this->timeMode : null;
    }

    /** Data de referință VALIDATĂ (fallback: azi). */
    public function timeRef(): CarbonImmutable
    {
        if (is_string($this->timeRef) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->timeRef) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $this->timeRef)->startOfDay();
            } catch (\Throwable) {
                // cade pe azi
            }
        }

        return CarbonImmutable::today();
    }

    public function setTimeMode(string $mode): void
    {
        $this->timeMode = in_array($mode, self::TIME_MODES, true) ? $mode : null;
        $this->timeRef = null;
        $this->resetTable();
    }

    /** Pasul perioadei: ±1 zi / săptămână / lună, după modul activ. */
    public function shiftTimePeriod(int $direction): void
    {
        $mode = $this->timeMode();

        if ($mode === null) {
            return;
        }

        $step = $direction >= 0 ? 1 : -1;
        $ref = $this->timeRef();

        $this->timeRef = match ($mode) {
            'zi' => $ref->addDays($step)->toDateString(),
            'saptamana' => $ref->addWeeks($step)->toDateString(),
            default => $ref->addMonthsNoOverflow($step)->toDateString(),
        };
        $this->resetTable();
    }

    public function goToTimeToday(): void
    {
        $this->timeRef = null;
        $this->resetTable();
    }

    public function timeRefIsToday(): bool
    {
        return $this->timeRef()->isToday();
    }

    /**
     * Intervalul [început, sfârșit] al perioadei active — null când modul e „Toate".
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function timeRange(): ?array
    {
        $ref = $this->timeRef();

        return match ($this->timeMode()) {
            'zi' => [$ref->startOfDay(), $ref->endOfDay()],
            'saptamana' => [$ref->startOfWeek(), $ref->endOfWeek()],
            'luna' => [$ref->startOfMonth(), $ref->endOfMonth()],
            default => null,
        };
    }

    /**
     * Pastilele barei temporale (Toate + cele 3 moduri).
     *
     * @return array<int, array{key: string, label: string, active: bool}>
     */
    public function timePills(): array
    {
        $active = $this->timeMode();

        $pills = [[
            'key' => 'toate',
            'label' => (string) __('panel.homework_time.all'),
            'active' => $active === null,
        ]];

        foreach (self::TIME_MODES as $mode) {
            $pills[] = [
                'key' => $mode,
                'label' => (string) __('panel.homework_time.'.$mode),
                'active' => $active === $mode,
            ];
        }

        return $pills;
    }

    /** Eticheta perioadei active („vineri, 18 iulie 2026" / „14–20 iul. 2026" / „iulie 2026"). */
    public function timePeriodLabel(): string
    {
        $range = $this->timeRange();

        if ($range === null) {
            return '';
        }

        [$start, $end] = $range;

        return match ($this->timeMode()) {
            'zi' => ucfirst($start->translatedFormat('l, j F Y')),
            'saptamana' => $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y'),
            default => ucfirst($start->translatedFormat('F Y')),
        };
    }

    /**
     * Contextul catalogului + constrângerea temporală pe data efectivă (termen ?? atribuire).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyCatalogContext(Builder $query): Builder
    {
        $query = $this->baseApplyCatalogContext($query);

        if (($range = $this->timeRange()) !== null) {
            $query->whereBetween(
                HomeworkAssignment::effectiveOnExpression(),
                [$range[0]->toDateString(), $range[1]->toDateString()],
            );
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Contextul curent (clasa → treaptă + literă, disciplina) pre-completează formularul.
            CreateAction::make()
                ->url(fn (): string => HomeworkAssignmentResource::getUrl('create', $this->catalogCreateUrlParameters())),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return HomeworkAssignmentResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        // Temele nu au anulare; cele retrase „moale" ies automat prin global scope.
        return HomeworkAssignmentResource::getEloquentQuery();
    }

    protected function catalogDateColumn(): string
    {
        return 'assigned_on';
    }

    /**
     * Temele nu au semestru → fără dimensiunea „Perioade".
     *
     * @return array<int, string>
     */
    protected function catalogDimensionKeys(): array
    {
        return ['clase', 'discipline', 'profesori'];
    }

    protected function catalogRecordsKey(): string
    {
        return 'panel.catalog_nav.homework_records';
    }

    /**
     * Clasa contextului → (treaptă, literă): tema clasei = tema cu litera ei SAU pe toată treapta
     * (litera goală). O clasă fără literă acceptă doar temele fără literă.
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToClass(Builder $query, ?SchoolClass $class): void
    {
        if ($class === null) {
            return;
        }

        $query
            ->where('grade_level', (int) $class->grade_level)
            ->where(function (Builder $q) use ($class) {
                $q->whereNull('section');

                if ($class->section !== null) {
                    $q->orWhere('section', $class->section);
                }
            });
    }

    /**
     * Temele nu au term_id — constrângerea de semestru e un no-op (defensiv; dimensiunea e scoasă).
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToTerm(Builder $query, ?Term $term): void
    {
        // intenționat gol
    }

    /**
     * Fără semestru la teme: un `?perioada=` rătăcit în URL nu rezolvă nimic (altfel ar ascunde
     * filtrul de semestru al tabelului fără să aplice vreo constrângere).
     */
    protected function resolvedTerm(): ?Term
    {
        return null;
    }

    /**
     * Agregatele cardurilor de clasă: numărate pe (treaptă, literă) și mapate înapoi pe clasele
     * navigabile — tema pe toată treapta contribuie la FIECARE clasă a treptei.
     *
     * @return Collection<int|string, \stdClass>
     */
    protected function classAggregates(): Collection
    {
        $rows = $this->catalogCountableQuery()
            ->toBase()
            ->selectRaw('grade_level, section, COUNT(*) AS aggregate, MAX(assigned_on) AS last_on')
            ->groupBy('grade_level', 'section')
            ->get();

        /** @var array<string, \stdClass> $byPair */
        $byPair = [];

        foreach ($rows as $row) {
            $byPair[$row->grade_level.'|'.($row->section ?? '')] = $row;
        }

        /** @var Collection<int|string, \stdClass> $aggregates */
        $aggregates = collect();

        foreach ($this->navigatorClasses() as $class) {
            $own = $byPair[$class->grade_level.'|'.($class->section ?? '')] ?? null;
            // Bucket-ul „toată treapta" se adaugă doar claselor CU literă (cele fără literă SUNT bucketul).
            $wholeGrade = $class->section !== null ? ($byPair[$class->grade_level.'|'] ?? null) : null;

            if ($own === null && $wholeGrade === null) {
                continue;
            }

            $lastDates = array_filter([$own->last_on ?? null, $wholeGrade->last_on ?? null]);

            $aggregates->put((int) $class->id, (object) [
                'aggregate' => (int) ($own->aggregate ?? 0) + (int) ($wholeGrade->aggregate ?? 0),
                'last_on' => $lastDates === [] ? null : max($lastDates),
            ]);
        }

        return $aggregates;
    }

    /**
     * Grupul-țintă distinct al unei teme = perechea treaptă+literă (nu un id de clasă).
     *
     * @return literal-string
     */
    protected function catalogDistinctClassExpression(): string
    {
        // Concatenare portabilă: SQLite folosește `||`, MySQL — CONCAT().
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(grade_level || '|' || COALESCE(section, ''))"
            : "CONCAT(grade_level, '|', COALESCE(section, ''))";
    }

    /**
     * Chips de CLASE în contextele disciplină / profesor: perechile (treaptă, literă) cu teme se
     * mapează pe clasele navigabile (tema pe toată treapta aprinde fiecare clasă a treptei), la
     * care se adaugă clasele din alocări.
     *
     * @param  \Closure(Builder<Model>): Builder<Model>  $constraint
     * @param  array<int, int>  $extraClassIds
     * @return array<int, array{id: int, label: string}>
     */
    protected function classChipsFor(\Closure $constraint, array $extraClassIds = []): array
    {
        /** @var Builder<Model> $query */
        $query = $this->catalogCountableQuery();
        $constraint($query);

        $pairs = $query
            ->toBase()
            ->selectRaw('DISTINCT grade_level, section')
            ->get();

        $chips = [];

        foreach ($this->navigatorClasses() as $class) {
            $eligible = in_array((int) $class->id, $extraClassIds, true);

            foreach ($pairs as $pair) {
                if ($eligible) {
                    break;
                }

                $eligible = (int) $pair->grade_level === (int) $class->grade_level
                    && ($pair->section === null || $pair->section === $class->section);
            }

            if ($eligible) {
                $chips[] = ['id' => (int) $class->id, 'label' => trim($class->name.' '.($class->section ?? ''))];
            }
        }

        return $chips;
    }
}
