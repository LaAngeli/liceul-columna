<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Utilizatori" = navigator pe ROLURI (2026-07-16, cerința beneficiarului: reorganizare
 * completă, nu filtre): carduri pe fiecare rol — cu descrierea rolului, numărul de conturi și
 * semnalele care cer atenție (suspendate / parole temporare) — apoi lista contextului. Toate
 * cele 9 roluri apar mereu (taxonomia e fixă; de pe cardul unui rol gol se creează primul cont,
 * pre-completat cu rolul). Conturile rătăcite FĂRĂ rol au bucket separat, doar când există.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.catalog.users-navigator';

    /** Rolul deschis (slug „dorit" din URL, validat la citire). */
    #[Url(as: 'rol', except: null)]
    public ?string $roleParam = null;

    /** Bucket-ul conturilor fără rol (apare doar când există astfel de conturi). */
    private const NO_ROLE = 'fara-rol';

    /** @var array<string, array{total: int, suspended: int, temp: int}>|null */
    private ?array $roleCountsMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Din contextul unui rol, formularul vine pre-completat cu rolul (validat acolo).
                ->url(function (): string {
                    $role = $this->activeRole();

                    return UserResource::getUrl('create', $role !== null && $role !== self::NO_ROLE
                        ? ['rol' => $role]
                        : []);
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openRole(string $value): void
    {
        if ($this->roleIsVisible($value)) {
            $this->roleParam = $value;
        }
    }

    public function leaveRole(): void
    {
        $this->roleParam = null;
    }

    public function activeRole(): ?string
    {
        return ($this->roleParam !== null && $this->roleIsVisible($this->roleParam))
            ? $this->roleParam
            : null;
    }

    public function activeRoleLabel(): string
    {
        $role = $this->activeRole();

        if ($role === null) {
            return '';
        }

        return $role === self::NO_ROLE
            ? (string) __('panel.users_nav.no_role')
            : (UserRole::tryFrom($role)?->label() ?? $role);
    }

    /**
     * Constrângerea listei pe rolul activ (apelată din UsersTable).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyRoleContext(Builder $query): Builder
    {
        $role = $this->activeRole();

        if ($role === null) {
            return $query;
        }

        return $role === self::NO_ROLE
            ? $query->whereDoesntHave('roles')
            : $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Cardurile rolurilor, în ordinea ierarhiei: descriere + conturi + semnale
     * (suspendate ca badge; parole temporare în statistici).
     *
     * @return array<int, array{id: string, title: string, subtitle: string, stats: array<int, string>, badge: string|null}>
     */
    public function roleCards(): array
    {
        $counts = $this->roleCounts();

        $cards = [];

        foreach (UserRole::cases() as $role) {
            $row = $counts[$role->value] ?? ['total' => 0, 'suspended' => 0, 'temp' => 0];

            $stats = [(string) trans_choice('panel.users_nav.accounts', $row['total'], ['count' => $row['total']])];

            if ($row['temp'] > 0) {
                $stats[] = (string) __('panel.users_nav.temp_passwords', ['count' => $row['temp']]);
            }

            $cards[] = [
                'id' => $role->value,
                'title' => $role->label(),
                'subtitle' => (string) __('panel.users_nav.descriptions.'.$role->value),
                'stats' => $stats,
                'badge' => $row['suspended'] > 0
                    ? (string) __('panel.users_nav.suspended_count', ['count' => $row['suspended']])
                    : null,
            ];
        }

        // Conturile rătăcite (fără rol) — bucket vizibil doar când există.
        $noRole = $counts[self::NO_ROLE] ?? null;

        if ($noRole !== null && $noRole['total'] > 0) {
            $cards[] = [
                'id' => self::NO_ROLE,
                'title' => (string) __('panel.users_nav.no_role'),
                'subtitle' => (string) __('panel.users_nav.no_role_description'),
                'stats' => [(string) trans_choice('panel.users_nav.accounts', $noRole['total'], ['count' => $noRole['total']])],
                'badge' => $noRole['suspended'] > 0
                    ? (string) __('panel.users_nav.suspended_count', ['count' => $noRole['suspended']])
                    : null,
            ];
        }

        return $cards;
    }

    public function usersHint(): string
    {
        return (string) __('panel.users_nav.hint');
    }

    private function roleIsVisible(string $value): bool
    {
        if ($value === self::NO_ROLE) {
            return ($this->roleCounts()[self::NO_ROLE]['total'] ?? 0) > 0;
        }

        return UserRole::tryFrom($value) !== null;
    }

    /**
     * Numărătorile per rol (o interogare) + suspendate + parole temporare + bucket-ul fără rol.
     *
     * @return array<string, array{total: int, suspended: int, temp: int}>
     */
    private function roleCounts(): array
    {
        if ($this->roleCountsMemo !== null) {
            return $this->roleCountsMemo;
        }

        /** @var Collection<int, \stdClass> $rows */
        $rows = User::query()
            ->toBase()
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name AS role_name, COUNT(*) AS total, SUM(CASE WHEN users.suspended_at IS NOT NULL THEN 1 ELSE 0 END) AS suspended, SUM(CASE WHEN users.must_change_password = 1 THEN 1 ELSE 0 END) AS temp')
            ->groupBy('roles.name')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->role_name] = [
                'total' => (int) $row->total,
                'suspended' => (int) $row->suspended,
                'temp' => (int) $row->temp,
            ];
        }

        $counts[self::NO_ROLE] = [
            'total' => User::query()->doesntHave('roles')->count(),
            'suspended' => User::query()->doesntHave('roles')->whereNotNull('suspended_at')->count(),
            'temp' => 0,
        ];

        return $this->roleCountsMemo = $counts;
    }
}
