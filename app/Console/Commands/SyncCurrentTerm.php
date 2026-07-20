<?php

namespace App\Console\Commands;

use App\Actions\SyncCurrentTermFlag;
use App\Models\Term;
use Illuminate\Console\Command;

/**
 * Setează `is_current` pe semestrul care conține data curentă (după intervalele starts_on/ends_on),
 * ca flag-ul să nu rămână stale. Rulat zilnic din scheduler (vezi routes/console.php). Idempotent.
 * Logica de alegere + oglinda anului curent stau în {@see SyncCurrentTermFlag} — aceeași regulă e
 * folosită și de acțiunea „Sincronizează" din secțiunea Semestre (o singură sursă, două căi).
 */
class SyncCurrentTerm extends Command
{
    protected $signature = 'app:sync-current-term';

    protected $description = 'Setează is_current pe semestrul care conține data curentă (după intervalele de date)';

    public function handle(SyncCurrentTermFlag $sync): int
    {
        $current = $sync->run();

        if (! $current instanceof Term) {
            $this->warn('Niciun semestru cu interval definit — nimic de setat.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Semestrul curent: %s (%s – %s). Anul curent: %s.',
            $current->name,
            $current->starts_on?->toDateString() ?? '?',
            $current->ends_on?->toDateString() ?? '?',
            // `??` are semantică isset(): relația nelegată dă null fără `?->`.
            $current->academicYear->name ?? '?',
        ));

        return self::SUCCESS;
    }
}
