<?php

namespace App\Console\Commands;

use App\Actions\SyncHomeroomRole;
use Illuminate\Console\Command;

/**
 * Aduce rolurile existente în acord cu desemnarea de dirigenție (decizie 2026-07-27: rolul
 * „Diriginte" e DERIVAT, nu ales manual — vezi {@see SyncHomeroomRole}).
 *
 * De la momentul deciziei, observerul pe `school_classes` ține rolurile sincronizate singur;
 * comanda asta e pentru STAREA MOȘTENITĂ — conturile create înainte, unde eticheta a apucat să se
 * despartă de realitate — și pentru orice cale care scrie prin query builder, ocolind observerii
 * (`app:import-legacy`, `app:seed-demo-zone`). Idempotentă: a doua rulare nu mai are ce raporta.
 *
 * Dry-run implicit — pe roluri nu se scrie fără ca omul să vadă întâi lista.
 */
class SyncHomeroomRoles extends Command
{
    protected $signature = 'app:sync-homeroom-roles {--apply : Scrie efectiv (implicit: doar raportează)}';

    protected $description = 'Sincronizează rolul Profesor/Diriginte cu desemnarea de dirigenție';

    public function handle(SyncHomeroomRole $sync): int
    {
        $drifted = $sync->drifted();

        if ($drifted === []) {
            $this->info('Nimic de sincronizat: toate etichetele corespund desemnării.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Cont', 'Roluri acum', 'Dirigenție', 'Acțiune'],
            array_map(static fn (array $row): array => [
                (string) $row['user']->getKey(),
                (string) $row['user']->name,
                $row['user']->getRoleNames()->implode(', '),
                $row['user']->homeroomLabel() ?? '—',
                $row['action'],
            ], $drifted),
        );

        if (! $this->option('apply')) {
            $this->warn(count($drifted).' cont(uri) de sincronizat. Rulează din nou cu --apply pentru a scrie.');

            return self::SUCCESS;
        }

        $changed = 0;

        foreach ($drifted as $row) {
            if ($sync->forUser($row['user']) !== null) {
                $changed++;
            }
        }

        $this->info("Sincronizate: {$changed} cont(uri).");

        return self::SUCCESS;
    }
}
