<?php

namespace App\Filament\Resources\Terms\Pages;

use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\Terms\TermResource;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Semestrele, pe ANUL lor (navigatorul de configurare, 2026-07-16): pastile pe ani — anul
 * curent implicit — în locul listei plate care amesteca semestrele tuturor anilor.
 */
class ListTerms extends ListRecords
{
    use HasYearPillsTable;

    protected static string $resource = TermResource::class;

    protected string $view = 'filament.catalog.config-year-table';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.terms_hint');
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
}
