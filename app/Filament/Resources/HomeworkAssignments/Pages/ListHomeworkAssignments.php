<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pagina „Teme" folosește navigatorul drill-down, ADAPTAT modelului temelor: tema țintește o
 * treaptă + literă (nu un school_class_id) și nu are semestru — deci dimensiunile sunt Clase /
 * Discipline / Profesori (fără „Perioade"), iar constrângerea de clasă se traduce în
 * (grade_level, section), incluzând temele date pe TOATĂ treapta (litera goală).
 */
class ListHomeworkAssignments extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;

    protected static string $resource = HomeworkAssignmentResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

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
