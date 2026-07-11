<?php

namespace App\Console\Commands;

use App\Actions\ArchiveYearToTranscript;
use App\Models\AcademicYear;
use Illuminate\Console\Command;

/**
 * Închiderea anului școlar din CLI (echivalentul acțiunii „Arhivează în matricolă" din panou):
 * `php artisan app:archive-year 2025–2026` (nume) sau `php artisan app:archive-year 3` (id).
 */
class ArchiveYear extends Command
{
    protected $signature = 'app:archive-year {year : ID-ul sau numele anului școlar de arhivat}';

    protected $description = 'Arhivează mediile semestriale ale unui an școlar în foaia matricolă (Sem I/II + anuala)';

    public function handle(ArchiveYearToTranscript $archiver): int
    {
        $input = (string) $this->argument('year');

        $year = is_numeric($input)
            ? AcademicYear::query()->find((int) $input)
            : AcademicYear::query()->where('name', $input)->first();

        if ($year === null) {
            $this->error("Anul școlar „{$input}\" nu există.");

            return self::FAILURE;
        }

        $result = $archiver->run($year);

        $this->info("Arhivat: {$result['records']} rânduri de matricolă pentru {$result['students']} elevi (anul {$year->name}).");

        if ($result['skipped'] > 0) {
            $this->warn("{$result['skipped']} perechi elev–disciplină SĂRITE: elevii nu au înmatriculare în anul arhivat — verifică înmatriculările, apoi rulează din nou.");
        }

        return self::SUCCESS;
    }
}
