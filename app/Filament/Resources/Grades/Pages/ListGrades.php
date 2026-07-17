<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Grades\GradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pagina „Note" nu mai e o listă plată: se navighează prin meniu (clase / discipline /
 * profesori / perioade → entitate → tabel în context). Vezi HasCatalogNavigator.
 * Bara temporală ({@see HasTimeNavigator}) filtrează pe data acordării notei.
 */
class ListGrades extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;
    use HasTimeNavigator;

    protected static string $resource = GradeResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    protected function timeDateExpression(): string|Expression
    {
        return 'graded_on';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Contextul curent (clasa / disciplina) pre-completează formularul de adăugare.
            CreateAction::make()
                ->url(fn (): string => GradeResource::getUrl('create', $this->catalogCreateUrlParameters())),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return GradeResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        // Numărătorile cardurilor reflectă doar notele ACTIVE (anulatele rămân în tabel, cu filtru).
        return GradeResource::getEloquentQuery()->whereNull('annulled_at');
    }

    protected function catalogDateColumn(): string
    {
        return 'graded_on';
    }
}
