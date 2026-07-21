<?php

namespace App\Console\Commands;

use App\Models\DatabaseNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Măturarea zilnică de RETENȚIE a notificărilor (cerința beneficiarului, 2026-07-21): notificările
 * CITITE mai vechi de `notifications.archive_after_days` (implicit 30) sunt mutate automat în arhivă
 * — nu șterse. Necititele NU se arhivează niciodată, indiferent de vechime (rămân în lista
 * principală până sunt citite). Rulează din scheduler, fără intervenția utilizatorului.
 *
 * Politica viitoare de ștergere definitivă e DORMANTĂ: se activează doar setând
 * `NOTIFICATIONS_PURGE_ARCHIVED_AFTER_YEARS` în .env — atunci notificările ARHIVATE mai vechi de
 * atâția ani se șterg (query builder, bulk). Cât timp variabila lipsește, nimic nu se șterge.
 */
class ArchiveNotifications extends Command
{
    protected $signature = 'app:archive-notifications {--dry-run : Doar raportează, fără nicio modificare}';

    protected $description = 'Arhivează notificările citite mai vechi de perioada configurată (și aplică politica de purge, dacă e activată)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) config('notifications.archive_after_days', 30));
        $cutoff = now()->subDays($days);

        // De arhivat: CITITE, nearhivate, mai vechi decât pragul. Necititele sunt excluse prin
        // definiție — vechimea nu le atinge.
        $archivable = DatabaseNotification::query()
            ->whereNull('archived_at')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff);

        $toArchive = (clone $archivable)->count();

        if ($dryRun) {
            $this->comment("DRY-RUN: {$toArchive} notificări citite mai vechi de {$days} zile ar fi arhivate. Nimic modificat.");
        } elseif ($toArchive > 0) {
            // Bulk update prin query builder (fără evenimente de model) — măturarea e o operațiune
            // de sistem, nu o acțiune de utilizator.
            $archivable->update(['archived_at' => now()]);
            $this->info("Arhivate: {$toArchive} notificări citite mai vechi de {$days} zile.");
            Log::info("Retenție notificări: {$toArchive} arhivate (prag {$days} zile).");
        } else {
            $this->info('Nimic de arhivat.');
        }

        $this->purgeIfConfigured($dryRun);

        return self::SUCCESS;
    }

    /**
     * Politica viitoare de ștergere definitivă — implicit OPRITĂ (config null). Se activează doar
     * prin decizie explicită (.env), fără modificări de cod.
     */
    private function purgeIfConfigured(bool $dryRun): void
    {
        $years = config('notifications.purge_archived_after_years');

        if ($years === null || $years === '' || (int) $years < 1) {
            return;
        }

        $years = (int) $years;

        $purgeable = DatabaseNotification::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subYears($years));

        $toPurge = (clone $purgeable)->count();

        if ($dryRun) {
            $this->comment("DRY-RUN: {$toPurge} notificări arhivate de peste {$years} ani ar fi șterse definitiv.");

            return;
        }

        if ($toPurge > 0) {
            $purgeable->delete();
            $this->warn("Purge: {$toPurge} notificări arhivate de peste {$years} ani șterse definitiv (politică explicită).");
            Log::warning("Retenție notificări: {$toPurge} șterse definitiv (arhivate de peste {$years} ani).");
        }
    }
}
