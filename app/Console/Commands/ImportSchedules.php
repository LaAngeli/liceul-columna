<?php

namespace App\Console\Commands;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use App\Support\OrareSchedules;
use Illuminate\Console\Command;

/**
 * Migrează orarele STATICE (App\Support\OrareSchedules) în tabelul `schedules` — sursa unică,
 * editabilă din panou și citită pe site. Idempotentă: sare peste tipurile deja populate; cu
 * `--fresh` reimportă de la zero (atenție: șterge editările din panou).
 */
class ImportSchedules extends Command
{
    protected $signature = 'app:import-schedules {--fresh : Șterge orarele existente înainte de import}';

    protected $description = 'Migrează orarele statice (OrareSchedules) în tabelul schedules (sursă unică editabilă).';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            Schedule::query()->forceDelete();
        }

        $imported = 0;

        foreach (OrareSchedules::all() as $type => $tables) {
            // Doar slug-urile care corespund celor 9 tipuri definite.
            if (ScheduleType::tryFrom((string) $type) === null) {
                continue;
            }

            // Idempotent fără --fresh: nu dubla un tip deja importat.
            if (! $this->option('fresh') && Schedule::query()->where('type', $type)->exists()) {
                continue;
            }

            foreach ($tables as $i => $table) {
                Schedule::create([
                    'type' => $type,
                    'label' => $table['label'],
                    'headers' => $table['headers'],
                    'rows' => $table['rows'],
                    'position' => $i,
                    'is_public' => true,
                ]);
                $imported++;
            }
        }

        $this->info("Orare importate: {$imported}.");

        return self::SUCCESS;
    }
}
