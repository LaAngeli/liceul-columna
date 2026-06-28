<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Consolidarea automată a absențelor nemotivate (spec §2.1): dacă a trecut termenul de depunere
 * (occurred_on + 5 zile lucrătoare) și nu există nicio cerere de motivare ÎN AȘTEPTARE care să
 * acopere ziua, absența se „blochează" definitiv ca nemotivată. Motivarea ulterioară e posibilă doar
 * prin EXCEPȚIE aprobată de vicedirectorul pe educație. De rulat zilnic (scheduler).
 */
class ConsolidateAbsences extends Command
{
    protected $signature = 'app:consolidate-absences';

    protected $description = 'Blochează ca nemotivate absențele al căror termen de motivare a expirat.';

    public function handle(): int
    {
        $today = Carbon::today();

        $candidates = Absence::query()
            ->where('is_motivated', false)
            ->whereNull('motivation_locked_at')
            ->whereNotNull('motivation_deadline')
            ->whereDate('motivation_deadline', '<', $today)
            ->get();

        $locked = 0;

        foreach ($candidates as $absence) {
            // O cerere ÎN AȘTEPTARE care acoperă ziua absenței ține fereastra deschisă.
            $covered = AbsenceMotivation::query()
                ->where('student_id', $absence->student_id)
                ->where('status', RequestStatus::Pending)
                ->whereDate('period_start', '<=', $absence->occurred_on)
                ->whereDate('period_end', '>=', $absence->occurred_on)
                ->exists();

            if ($covered) {
                continue;
            }

            // Update prin model (nu bulk) → schimbarea e auditată (Absence e Auditable).
            $absence->update(['motivation_locked_at' => now()]);
            $locked++;
        }

        $this->info("Absențe consolidate ca nemotivate: {$locked}.");

        return self::SUCCESS;
    }
}
