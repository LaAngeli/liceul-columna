<?php

namespace App\Support;

use App\Enums\RestorableType;
use App\Models\Audit;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Inventarul coșului: CE e șters, de CÂND și de CINE.
 *
 * „De cine" nu e o coloană pe modele — se citește din jurnalul de audit (evenimentul `deleted`),
 * încărcat în BLOC pentru toată lista, nu per rând: coșul e locul unde te uiți când ceva a
 * dispărut, iar prima întrebare e mereu „cine l-a șters", nu „ce a fost".
 */
class RestoreInventory
{
    /** @var array<string, array<int, array{name: string|null, at: Carbon|null}>> */
    private array $deletionMemo = [];

    /**
     * Câte înregistrări șterse are fiecare tip.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (RestorableType::cases() as $type) {
            $counts[$type->value] = $type->modelClass()::query()->onlyTrashed()->count();
        }

        return $counts;
    }

    /** Data ultimei ștergeri dintr-un tip — „ce s-a întâmplat recent" pe cardul de categorie. */
    public function lastDeletedAt(RestorableType $type): ?Carbon
    {
        $value = $type->modelClass()::query()->onlyTrashed()->max('deleted_at');

        return $value !== null ? Carbon::parse($value) : null;
    }

    /**
     * Înregistrările șterse ale unui tip, cele mai recente întâi.
     *
     * @return Collection<int, Student>|Collection<int, Teacher>|Collection<int, SchoolClass>|Collection<int, Enrollment>|Collection<int, Subject>
     */
    public function records(RestorableType $type, int $limit = 100): Collection
    {
        return $type->modelClass()::query()
            ->onlyTrashed()
            ->with($type->eagerLoads())
            ->orderByDesc('deleted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cine și când a șters fiecare înregistrare din listă — o singură interogare pe jurnal,
     * indexată pe id.
     *
     * @param  Collection<int, Student>|Collection<int, Teacher>|Collection<int, SchoolClass>|Collection<int, Enrollment>|Collection<int, Subject>  $records
     * @return array<int, array{name: string|null, at: Carbon|null}>
     */
    public function deletionContext(RestorableType $type, Collection $records): array
    {
        $ids = $records->modelKeys();

        if ($ids === []) {
            return [];
        }

        $key = $type->value.':'.implode(',', $ids);

        if (isset($this->deletionMemo[$key])) {
            return $this->deletionMemo[$key];
        }

        $context = [];

        $entries = Audit::query()
            ->where('auditable_type', $type->modelClass())
            ->whereIn('auditable_id', $ids)
            ->where('event', 'deleted')
            ->orderByDesc('created_at')
            ->get();

        // Numele actorilor într-o singură interogare (relația `user` a jurnalului e morfică și
        // nu se poate încărca tipat) — coșul afișează nume, nu id-uri.
        $actors = User::query()
            ->whereKey($entries->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        foreach ($entries as $audit) {
            $id = (int) $audit->auditable_id;

            // Prima intrare întâlnită e cea mai recentă (ordonare descrescătoare) — ștergerile
            // repetate (șters → restaurat → șters) păstrează ultima, nu prima.
            if (! array_key_exists($id, $context)) {
                $context[$id] = [
                    'name' => $audit->user_id !== null ? $actors->get($audit->user_id) : null,
                    'at' => $audit->created_at,
                ];
            }
        }

        $this->deletionMemo[$key] = $context;

        return $context;
    }
}
