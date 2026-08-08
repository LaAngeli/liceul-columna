<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pagina „Teme" folosește navigatorul drill-down, ADAPTAT modelului temelor: tema nu are semestru
 * — deci dimensiunile sunt Clase / Discipline / Profesori (fără „Perioade") — iar ținta ei e clasa
 * (`school_class_id`) SAU, la temele pe toată treapta și la rândurile vechi, perechea
 * (grade_level, section). Ambele găleți se adună în agregatele și cipurile de mai jos.
 *
 * TIMPUL e a doua axă ({@see HasTimeNavigator}): bara Toate / Zi / Săptămână / Lună filtrează
 * pe DATA LECȚIEI (assigned_on) — axa unică după eliminarea „termenului" (2026-07-31).
 */
class ListHomeworkAssignments extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;
    use HasTimeNavigator;

    protected static string $resource = HomeworkAssignmentResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    /** Vara, „azi" nu e zi de școală: registrul se deschide pe ultima zi de curs, nu pe un tabel gol. */
    protected function anchorsToSchoolYear(): bool
    {
        return true;
    }

    protected function timeDateExpression(): string|Expression
    {
        return 'assigned_on';
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
     * Clasa contextului: tema ei EXACTĂ (school_class_id) sau, pentru rândurile fără clasă, cele
     * pe toată treapta. Regula trăiește într-un singur loc — scope-ul modelului.
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToClass(Builder $query, ?SchoolClass $class): void
    {
        if ($class === null) {
            return;
        }

        /** @var Builder<HomeworkAssignment> $query */
        $query->forClass($class);
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
     * Agregatele cardurilor de clasă, din DOUĂ găleți care se adună:
     *   • temele cu clasă → numărate pe `school_class_id`, exact clasa lor;
     *   • temele fără clasă → pe (treaptă, literă), mapate pe clasele navigabile; cele pe toată
     *     treapta contribuie la FIECARE clasă a treptei.
     * Separarea e ce împiedică cardurile claselor omonime din ani diferiți să afișeze același număr.
     *
     * @return Collection<int|string, \stdClass>
     */
    protected function classAggregates(): Collection
    {
        $byClass = $this->catalogCountableQuery()
            ->toBase()
            ->whereNotNull('school_class_id')
            ->selectRaw('school_class_id, COUNT(*) AS aggregate, MAX(assigned_on) AS last_on')
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');

        $rows = $this->catalogCountableQuery()
            ->toBase()
            ->whereNull('school_class_id')
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
            $exact = $byClass[(int) $class->id] ?? null;
            $own = $byPair[$class->grade_level.'|'.($class->section ?? '')] ?? null;
            // Bucket-ul „toată treapta" se adaugă doar claselor CU literă (cele fără literă SUNT bucketul).
            $wholeGrade = $class->section !== null ? ($byPair[$class->grade_level.'|'] ?? null) : null;

            if ($exact === null && $own === null && $wholeGrade === null) {
                continue;
            }

            $lastDates = array_filter([$exact->last_on ?? null, $own->last_on ?? null, $wholeGrade->last_on ?? null]);

            $aggregates->put((int) $class->id, (object) [
                'aggregate' => (int) ($exact->aggregate ?? 0)
                    + (int) ($own->aggregate ?? 0)
                    + (int) ($wholeGrade->aggregate ?? 0),
                'last_on' => $lastDates === [] ? null : max($lastDates),
            ]);
        }

        return $aggregates;
    }

    /**
     * Grupul-țintă distinct al unei teme: clasa, când o poartă, altfel perechea treaptă+literă.
     * COALESCE alege prima ramură pentru că, la ambele motoare, concatenarea cu NULL dă NULL.
     *
     * @return literal-string
     */
    protected function catalogDistinctClassExpression(): string
    {
        // Concatenare portabilă: SQLite folosește `||`, MySQL — CONCAT().
        return DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE('c' || school_class_id, grade_level || '|' || COALESCE(section, ''))"
            : "COALESCE(CONCAT('c', school_class_id), CONCAT(grade_level, '|', COALESCE(section, '')))";
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

        $targets = $query
            ->toBase()
            ->selectRaw('DISTINCT school_class_id, grade_level, section')
            ->get();

        $chips = [];

        foreach ($this->navigatorClasses() as $class) {
            $eligible = in_array((int) $class->id, $extraClassIds, true);

            foreach ($targets as $target) {
                if ($eligible) {
                    break;
                }

                // Tema cu clasă aprinde EXACT clasa ei; cea fără clasă, perechea (litera goală =
                // toată treapta). Aceeași ramificare ca scopul modelului.
                $eligible = $target->school_class_id !== null
                    ? (int) $target->school_class_id === (int) $class->id
                    : (int) $target->grade_level === (int) $class->grade_level
                        && ($target->section === null || $target->section === $class->section);
            }

            if ($eligible) {
                $chips[] = ['id' => (int) $class->id, 'label' => trim($class->name.' '.($class->section ?? ''))];
            }
        }

        return $chips;
    }
}
