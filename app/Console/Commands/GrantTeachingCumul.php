<?php

namespace App\Console\Commands;

use App\Actions\SyncHomeroomRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * CUMUL-ul pedagogic pentru conturile MOȘTENITE: diriginții care predau primesc și rolul Profesor
 * (decizia beneficiarului 2026-07-31, „varianta A" din analiza de impact).
 *
 * De ce e nevoie: importul legacy a mapat `func=3` → rol unic „diriginte", deși în realitate
 * dirigintele e MEREU și profesor (măsurat: 20 din 22 de conturi reale predau, zero sunt „doar
 * diriginte"). Rămase mono-rol, aceste conturi nu primesc comutatorul de context și văd o listă
 * FUZIONATĂ — exact ce documentul beneficiarului cerea să se separe (pct. 5).
 *
 * DREPTURILE NU SE SCHIMBĂ: contul vede exact aceleași clase ca înainte (predate ∪ dirigenție).
 * Se schimbă doar CUM le vede — două contexte între care comută, în loc de o listă unică.
 *
 * ⚠️ ATERIZAREA IMPLICITĂ. Prioritatea rolurilor pune Diriginte înaintea Profesorului, deci fără
 * corecție un cadru care predă la 14 clase și e diriginte la 1 ar ateriza în contextul Diriginte
 * și ar vedea O SINGURĂ clasă — ar crede că i-au dispărut celelalte 13. De aceea comanda setează
 * `preferred_role = profesor`: aterizezi în munca de zi cu zi (predatul), iar dirigenția rămâne
 * la un click, pentru lucrul periodic (motivări, situația clasei).
 *
 * Idempotentă, dry-run implicit. Conturile care poartă deja ambele roluri nu sunt atinse; cele
 * cu roluri din afara corpului didactic (conducere, familie) nu intră niciodată sub regulă.
 */
class GrantTeachingCumul extends Command
{
    protected $signature = 'app:grant-teaching-cumul {--apply : Scrie efectiv (implicit: doar raportează)}';

    protected $description = 'Acordă rolul Profesor diriginților care predau (cumul multi-rol) + aterizare implicită pe Profesor';

    public function handle(SyncHomeroomRole $sync): int
    {
        $candidates = $sync->missingTeacherMembership();

        if ($candidates === []) {
            $this->info('Nimic de acordat: toate conturile care predau poartă deja rolul Profesor.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Cont', 'Roluri acum', 'Predă', 'Dirigenție', 'După cumul'],
            array_map(static function (User $user): array {
                $teacher = $user->teacher;

                return [
                    (string) $user->getKey(),
                    (string) $user->name,
                    $user->getRoleNames()->implode(', '),
                    (string) count($teacher?->taughtSchoolClassIds() ?? []),
                    (string) count($teacher?->homeroomSchoolClassIds() ?? []),
                    'aterizare pe „Profesor"',
                ];
            }, $candidates),
        );

        if (! $this->option('apply')) {
            $this->warn(count($candidates).' cont(uri) de cumulat. Rulează din nou cu --apply pentru a scrie.');
            $this->line('Drepturile NU se schimbă — doar se separă pe contexte, cu comutator în bara de sus.');

            return self::SUCCESS;
        }

        $granted = 0;

        foreach ($candidates as $user) {
            if ($sync->grantTeacherMembership($user) === null) {
                continue;
            }

            // Aterizarea implicită pe contextul de zi cu zi (vezi docblock).
            $user->forceFill(['preferred_role' => UserRole::Profesor->value])->save();
            $granted++;
        }

        $this->info("Cumul acordat: {$granted} cont(uri), cu aterizare implicită pe „Profesor”.");

        return self::SUCCESS;
    }
}
