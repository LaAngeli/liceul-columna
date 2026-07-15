<?php

namespace App\Filament\Resources\SchoolClasses\Pages;

use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Models\AcademicYear;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clasele se navighează pe ANI ȘCOLARI (tab-uri, anul curent implicit) — lista amesteca toți
 * anii, inclusiv arhiva legacy, într-un singur tabel. La sub ~10 ani, tab-urile native sunt
 * unealta potrivită (nu navigatorul cu carduri, gândit pentru zeci de entități).
 */
class ListSchoolClasses extends ListRecords
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = [];

        // Numărat prin interogarea SCOPED a resursei (profesorul își vede doar clasele lui) —
        // un badge cu totalul școlii ar contrazice tabelul. Anii fără clase vizibile nu apar.
        $visibleCounts = SchoolClassResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id');

        $years = AcademicYear::query()
            ->whereKey($visibleCounts->keys()->all())
            ->orderByDesc('id')
            ->get();

        foreach ($years as $year) {
            $tabs['an-'.$year->id] = Tab::make($year->name)
                ->badge((int) $visibleCounts->get($year->id))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('academic_year_id', $year->id));
        }

        $tabs['all'] = Tab::make(__('panel.common.all'))
            ->icon('heroicon-o-squares-2x2');

        return $tabs;
    }

    /** Tab-ul implicit = anul școlar CURENT (după semestrul activ), nu primul din listă. */
    public function getDefaultActiveTab(): string|int|null
    {
        $currentYearId = Term::query()->where('is_current', true)->value('academic_year_id');

        return $currentYearId !== null ? 'an-'.$currentYearId : 'all';
    }
}
