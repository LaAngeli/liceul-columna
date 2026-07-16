<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use App\Models\CorigentaSession;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sesiunile de corigență, pe ANUL lor (navigatorul de configurare, 2026-07-16): pastile pe ani —
 * anul curent implicit — fluxul de propunere/aprobare rămâne pe tabel.
 */
class ListCorigentaSessions extends ListRecords
{
    use HasYearPillsTable;

    protected static string $resource = CorigentaSessionResource::class;

    protected string $view = 'filament.catalog.config-year-table';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.sessions_hint');
    }

    protected function yearRecordCounts(): Collection
    {
        return CorigentaSession::query()
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
