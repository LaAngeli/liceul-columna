<?php

namespace App\Console\Commands;

use App\Actions\ImportLegacyUsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportUsers extends Command
{
    protected $signature = 'app:import-users';

    protected $description = 'Creează utilizatori reali din conturile de login vechi (bdn_users), legate prin nume de fișele de elev/profesor.';

    public function handle(ImportLegacyUsers $action): int
    {
        if (DB::table('students')->count() === 0 || DB::table('teachers')->count() === 0) {
            $this->error('Nu există elevi/profesori. Rulează întâi `php artisan app:import-legacy`.');

            return self::FAILURE;
        }

        $stats = $action->execute();

        $this->info('Import conturi finalizat.');
        $this->table(['Metrică', 'Valoare'], ImportLegacyUsers::summaryRows($stats));
        $this->warn('Toți userii migrați au `must_change_password=true` — schimbă parola la prima logare.');

        return self::SUCCESS;
    }
}
