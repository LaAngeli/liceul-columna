<?php

namespace App\Console\Commands;

use App\Actions\Cms\CheckContentHealth;
use Illuminate\Console\Command;

/**
 * Raportul detaliat al verificării de integritate a conținutului /studio (vezi
 * {@see CheckContentHealth}). Widgetul de pe dashboard arată doar totalurile; comanda listează
 * fiecare problemă în parte. Utilă și în scheduler/cron pentru monitorizare periodică.
 */
class ContentHealthCheck extends Command
{
    protected $signature = 'app:content-health';

    protected $description = 'Verifică integritatea conținutului /studio: referințe rupte (DB → fișiere lipsă) și fișiere orfane (disk → nereferențiate).';

    public function handle(CheckContentHealth $checker): int
    {
        $report = $checker->run();

        if ($report['broken'] === []) {
            $this->info('Referințe rupte: niciuna — toate fișierele referențiate există pe disk.');
        } else {
            $this->error('Referințe rupte ('.count($report['broken']).'):');
            foreach ($report['broken'] as $issue) {
                $this->line('  · '.$issue);
            }
        }

        if ($report['orphans'] === []) {
            $this->info('Fișiere orfane: niciunul.');
        } else {
            $this->warn('Fișiere orfane ('.count($report['orphans']).') — pe disk, dar nereferențiate de conținut:');
            foreach ($report['orphans'] as $orphan) {
                $this->line('  · '.$orphan);
            }
        }

        if ($report['external'] > 0) {
            $this->comment('Referințe externe (http, neverificate): '.$report['external'].'.');
        }

        return self::SUCCESS;
    }
}
