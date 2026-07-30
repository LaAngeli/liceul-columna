<?php

namespace App\Filament\Content\Widgets;

use App\Actions\Cms\CheckContentHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Monitorul de integritate de pe dashboard: rulează {@see CheckContentHealth} LIVE la fiecare
 * încărcare (fără cache — reflectă mereu starea reală, inclusiv modificările făcute direct pe
 * disk sau în DB). Rămâne lazy (spre deosebire de ContentOverview) ca primul paint al panoului
 * să nu aștepte scanarea fișierelor; detaliile per problemă: `php artisan app:content-health`.
 */
class ContentHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Integritatea conținutului';

    protected ?string $description = 'Verificare live DB ↔ fișiere la fiecare încărcare. Detalii: php artisan app:content-health.';

    protected function getStats(): array
    {
        $report = app(CheckContentHealth::class)->run();

        $broken = count($report['broken']);
        $orphans = count($report['orphans']);

        return [
            Stat::make('Referințe rupte', (string) $broken)
                ->description($broken === 0
                    ? 'Toate fișierele referențiate există pe disk'
                    : $report['broken'][0].($broken > 1 ? '  (+'.($broken - 1).')' : ''))
                ->color($broken === 0 ? 'success' : 'danger'),

            Stat::make('Fișiere orfane', (string) $orphans)
                ->description($orphans === 0
                    ? 'Niciun fișier nereferențiat în stocarea media'
                    : $report['orphans'][0].($orphans > 1 ? '  (+'.($orphans - 1).')' : ''))
                ->color($orphans === 0 ? 'success' : 'warning'),
        ];
    }
}
