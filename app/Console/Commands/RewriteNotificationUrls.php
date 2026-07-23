<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Support\CabinetLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrare de DATE (unică): notificările emise ÎNAINTE de 93e96e6 poartă ținta veche
 * `/cabinet/elev/{id}` — profilul general — deși conținutul lor e despre o secțiune anume.
 * Codul nou rutează corect ({@see CabinetLinks}), dar rândurile deja livrate rămân pe vechea
 * destinație: click-ul funcționează, însă aterizează pe „Privire de ansamblu", nu la absențe/note.
 *
 * Comanda le REMAPEAZĂ, cu trei precauții:
 *   • DRY-RUN implicit (`--apply` scrie efectiv) — se vede exact ce s-ar schimba, per tip;
 *   • rescrie AMBELE locuri unde stă URL-ul: `data.url` ȘI `data.actions[*].url` (payload-ul
 *     Filament duplică ținta în acțiunea „Deschide" a clopoțelului — dacă se rescrie doar `url`,
 *     butonul din panou rămâne pe vechea destinație);
 *   • idempotentă: atinge DOAR forma veche exactă (`/cabinet/elev/{id}`, fără query), deci o a
 *     doua rulare nu mai găsește nimic, iar notificările corecte nu se ating niciodată.
 *
 * NU se remapează tipurile a căror destinație a rămas fișa elevului (ex. `status_change`:
 * confirmarea de statut corigent/amânat se citește chiar pe profil).
 */
class RewriteNotificationUrls extends Command
{
    protected $signature = 'app:rewrite-notification-urls {--apply : Scrie efectiv (implicit: doar raportează)}';

    protected $description = 'Remapează notificările vechi de la profilul general la secțiunea relevantă';

    /** Forma VECHE, exactă: cale de profil fără query (`/cabinet/elev/123`). */
    private const OLD_URL = '#^/cabinet/elev/(\d+)$#';

    /**
     * Tip de notificare → destinația de azi. Sursa e {@see CabinetLinks}, aceeași pe care o
     * folosesc observerii — dacă o destinație se schimbă vreodată, se schimbă într-un singur loc.
     *
     * @return array<string, callable(int): string>
     */
    private function destinations(): array
    {
        return [
            NotificationType::NewGrade->value => CabinetLinks::grades(...),
            NotificationType::GradeAnnulled->value => CabinetLinks::grades(...),
            NotificationType::GradeCorrected->value => CabinetLinks::grades(...),
            NotificationType::NewAbsence->value => CabinetLinks::absenceRegister(...),
            NotificationType::AbsenceMotivationDecided->value => CabinetLinks::motivations(...),
            NotificationType::ContestationRejected->value => CabinetLinks::requests(...),
            NotificationType::CorigentaResult->value => CabinetLinks::requests(...),
        ];
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $destinations = $this->destinations();

        /** @var array<string, int> $changed */
        $changed = [];
        $total = 0;

        DB::table('notifications')
            ->select('id', 'data')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($destinations, $apply, &$changed, &$total): void {
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $type = is_string($data['type'] ?? null) ? $data['type'] : null;
                    $url = is_string($data['url'] ?? null) ? $data['url'] : null;

                    if ($type === null || $url === null || ! isset($destinations[$type])) {
                        continue;
                    }

                    if (preg_match(self::OLD_URL, $url, $m) !== 1) {
                        continue; // deja migrată sau altă formă → nu se atinge
                    }

                    $fresh = $destinations[$type]((int) $m[1]);
                    $data['url'] = $fresh;

                    // Payload-ul Filament duplică ținta în acțiunea „Deschide" — o aliniem.
                    if (isset($data['actions']) && is_array($data['actions'])) {
                        foreach ($data['actions'] as $i => $action) {
                            if (is_array($action) && ($action['url'] ?? null) === $url) {
                                $data['actions'][$i]['url'] = $fresh;
                            }
                        }
                    }

                    $total++;
                    $changed[$type] = ($changed[$type] ?? 0) + 1;

                    if ($apply) {
                        // Query builder + doar coloana `data`: nu atingem `updated_at` și nu
                        // declanșăm evenimente de model pe un rând de istoric.
                        DB::table('notifications')
                            ->where('id', $row->id)
                            ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                }
            });

        if ($total === 0) {
            $this->info('Nicio notificare de remapat — totul e deja pe destinațiile noi.');

            return self::SUCCESS;
        }

        ksort($changed);
        $this->table(
            ['Tip notificare', 'Rânduri'],
            array_map(static fn (string $type, int $count): array => [$type, $count], array_keys($changed), $changed),
        );

        if ($apply) {
            $this->info("Remapate: {$total} notificări.");
        } else {
            $this->warn("DRY-RUN — nimic nu a fost scris. {$total} notificări ar fi remapate.");
            $this->line('Rulează din nou cu --apply pentru a aplica.');
        }

        return self::SUCCESS;
    }
}
