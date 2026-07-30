<?php

namespace App\Audit;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Drivers\Database;

/**
 * Driverul de audit care consemnează CALITATEA actorului, nu doar identitatea lui.
 *
 * De ce: spec §3.1 cere ca fiecare acțiune să fie înregistrată „sub rolul activ", iar drepturile
 * cele mai sensibile (validarea motivărilor de absență) NU vin din rolul contului, ci din
 * desemnarea de dirigenție. Fără instantaneu, fișa de jurnal afișa rolul CURENT — deci o
 * reatribuire de dirigenție sau o promovare rescria retroactiv istoria: nu se mai putea
 * reconstitui de unde venise dreptul la momentul acțiunii.
 *
 * Se montează prin container ({@see AppServiceProvider}), înlocuind driverul
 * `database` al pachetului — config-ul `audit.driver` rămâne neatins, la fel scrierea propriu-zisă.
 *
 * Costul: zero interogări în plus în cazul obișnuit (actorul e utilizatorul autentificat, deja în
 * memorie), iar rezultatul e memoizat cât trăiește instanța driverului — pe web, o cerere: o
 * acțiune care atinge mai multe modele auditabile calculează calitatea o singură dată. Memoizarea
 * nu poate deveni stale într-un proces de lungă durată, fiindcă în consolă (deci și în workerii de
 * queue) auditarea e oprită din `audit.console`.
 */
class RecordsActorCapacity extends Database
{
    /**
     * Calitatea deja calculată în cererea curentă, pe id de utilizator.
     *
     * @var array<int, array{role: string|null, capacity: string|null}>
     */
    private array $resolved = [];

    public function audit(Auditable $model): ?Audit
    {
        return $model->withSavepointIfNeeded(
            fn () => $model->audits()->getModel()::create($this->withActorContext($model->toAudit())),
        );
    }

    /**
     * Adaugă instantaneul actorului peste datele produse de pachet. Nu atinge nimic altceva:
     * dacă actorul nu se poate stabili (acțiune de sistem, cont șters între timp), coloanele
     * rămân null — un context lipsă se spune, nu se inventează.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withActorContext(array $data): array
    {
        $morphPrefix = (string) config('audit.user.morph_prefix', 'user');
        $userId = $data[$morphPrefix.'_id'] ?? null;

        if (! is_int($userId) && ! is_string($userId)) {
            return $data;
        }

        $context = $this->contextFor((int) $userId);

        $data['actor_role'] = $context['role'];
        $data['actor_capacity'] = $context['capacity'];

        return $data;
    }

    /**
     * @return array{role: string|null, capacity: string|null}
     */
    private function contextFor(int $userId): array
    {
        if (array_key_exists($userId, $this->resolved)) {
            return $this->resolved[$userId];
        }

        // Cazul normal: actorul E utilizatorul autentificat, deja hidratat — fără interogare.
        // Rezolverul poate întoarce însă alt id (impersonare, guard secundar), deci verificăm.
        $user = Auth::user();

        if (! $user instanceof User || $user->getKey() !== $userId) {
            $user = User::query()->whereKey($userId)->first();
        }

        // Rolul ACTIV, nu primul din listă: sub multi-rol (F1), jurnalul consemnează contextul
        // sub care s-a lucrat — „Ion Popescu [Diriginte] a motivat absența" (doc pct. 10).
        // Pentru mono-rol cele două coincid; fallback-ul păstrează comportamentul vechi.
        $active = $user?->activeRole();

        return $this->resolved[$userId] = [
            'role' => $active !== null ? $active->value : $user?->getRoleNames()->first(),
            'capacity' => $user?->auditCapacity(),
        ];
    }
}
