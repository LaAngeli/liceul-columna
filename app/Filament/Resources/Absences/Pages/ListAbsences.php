<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Absences\AbsenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pagina „Absențe" folosește același navigator drill-down ca „Note" (clase / discipline /
 * profesori / perioade → entitate → tabel în context). Vezi HasCatalogNavigator.
 */
class ListAbsences extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;

    protected static string $resource = AbsenceResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    protected function getHeaderActions(): array
    {
        return [
            // Contextul curent (clasa / disciplina) pre-completează formularul de consemnare.
            CreateAction::make()
                ->url(fn (): string => AbsenceResource::getUrl('create', $this->catalogCreateUrlParameters())),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return AbsenceResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        // Absențele nu au anulare (ca notele); cele șterse „moale" ies automat prin global scope.
        return AbsenceResource::getEloquentQuery();
    }

    protected function catalogDateColumn(): string
    {
        return 'occurred_on';
    }
}
