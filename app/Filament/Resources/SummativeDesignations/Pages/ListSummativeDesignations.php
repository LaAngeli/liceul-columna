<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use App\Models\SummativeDesignation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Designările de sumativă (obiect × clasă), pe ANUL clasei (navigatorul de configurare,
 * 2026-07-16): pastile pe ani — anul curent implicit; anul vine prin clasa desemnată.
 */
class ListSummativeDesignations extends ListRecords
{
    use HasYearPillsTable;

    protected static string $resource = SummativeDesignationResource::class;

    protected string $view = 'filament.catalog.config-year-table';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.summative_hint');
    }

    protected function yearRecordCounts(): Collection
    {
        return SummativeDesignation::query()
            ->toBase()
            ->join('school_classes', 'school_classes.id', '=', 'summative_designations.school_class_id')
            ->selectRaw('school_classes.academic_year_id AS year_id, COUNT(*) AS aggregate')
            ->groupBy('school_classes.academic_year_id')
            ->pluck('aggregate', 'year_id')
            ->map(fn ($count): int => (int) $count);
    }

    protected function constrainToYear(Builder $query, int $yearId): void
    {
        $query->whereHas('schoolClass', fn (Builder $q) => $q->where('academic_year_id', $yearId));
    }
}
