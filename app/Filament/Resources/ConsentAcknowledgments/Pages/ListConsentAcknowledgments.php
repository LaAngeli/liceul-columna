<?php

namespace App\Filament\Resources\ConsentAcknowledgments\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\ConsentAcknowledgments\ConsentAcknowledgmentResource;
use App\Models\ConsentAcknowledgment;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * „Consimțăminte" RESTRUCTURAT (cerința beneficiarului: „nu îmi e clară relevanța informației"):
 * secțiunea răspunde acum la întrebarea de conformitate — CINE a luat la cunoștință versiunea
 * CURENTĂ a notei de informare (L133 §7) și cine NU. Doar elevii și părinții sunt vizați
 * (personalul prelucrează datele pe temei de rol); confirmarea e forțată la prima logare.
 *
 * Aterizare = carduri pe segment (Elevi / Părinți) cu ACOPERIREA (X din Y, %) și restanța →
 * context pe segment cu două vederi: „Dovezi" (tabelul confirmărilor înregistrate) și
 * „De confirmat" (lista conturilor ACTIVE fără versiunea curentă — cu căutare).
 */
class ListConsentAcknowledgments extends ListRecords
{
    protected static string $resource = ConsentAcknowledgmentResource::class;

    protected string $view = 'filament.catalog.consents-navigator';

    /** Segmentul deschis (contextul): elev / parinte — validat la citire. */
    #[Url(as: 'rol', except: null)]
    public ?string $activeRole = null;

    /** Vederea „De confirmat" (restanța); implicit = dovezile înregistrate. */
    #[Url(as: 'restanta', except: null)]
    public ?string $missingMode = null;

    /** Căutarea în lista „De confirmat" (nume/utilizator). */
    public string $missingSearch = '';

    /** @var array<string, array{total: int, confirmed: int, proofs: int}>|null */
    private ?array $coverage = null;

    /** Rolurile vizate de nota de informare (familia; personalul NU e vizat). */
    private const TARGET_ROLES = [UserRole::Elev, UserRole::Parinte];

    public function activeRole(): ?UserRole
    {
        foreach (self::TARGET_ROLES as $role) {
            if ($this->activeRole === $role->value) {
                return $role;
            }
        }

        return null;
    }

    public function isMissingView(): bool
    {
        return $this->missingMode === '1';
    }

    public function openRole(string $role): void
    {
        $this->activeRole = UserRole::tryFrom($role) !== null && in_array(UserRole::from($role), self::TARGET_ROLES, true)
            ? $role
            : null;
        $this->missingMode = null;
        $this->missingSearch = '';
        $this->resetTable();
    }

    public function leaveRole(): void
    {
        $this->activeRole = null;
        $this->missingMode = null;
        $this->missingSearch = '';
        $this->resetTable();
    }

    public function setConsentView(string $view): void
    {
        $this->missingMode = $view === 'missing' ? '1' : null;
        $this->missingSearch = '';
        $this->resetTable();
    }

    /** Versiunea curentă a notei de informare (config) — afișată în hint și folosită la acoperire. */
    public function currentVersion(): string
    {
        return (string) config('privacy.notice_version');
    }

    public function consentHint(): string
    {
        return (string) __('panel.consent_nav.hint', ['version' => $this->currentVersion()]);
    }

    /**
     * Constrângerea contextului pe tabelul DOVEZILOR: doar confirmările segmentului deschis.
     *
     * @param  Builder<ConsentAcknowledgment>  $query
     * @return Builder<ConsentAcknowledgment>
     */
    public function applyConsentContext(Builder $query): Builder
    {
        $role = $this->activeRole();

        if ($role !== null) {
            $query->whereHas('user.roles', fn (Builder $roles) => $roles->where('name', $role->value));
        }

        return $query;
    }

    /**
     * Cardurile segmentelor vizate, cu acoperirea versiunii curente.
     *
     * @return array<int, array{id: string, title: string, badge: string|null, stats: array<int, string>, percent: int}>
     */
    public function roleCards(): array
    {
        $cards = [];

        foreach (self::TARGET_ROLES as $role) {
            $coverage = $this->coverage()[$role->value] ?? ['total' => 0, 'confirmed' => 0, 'proofs' => 0];
            $missing = max(0, $coverage['total'] - $coverage['confirmed']);
            $percent = $coverage['total'] > 0
                ? (int) floor($coverage['confirmed'] * 100 / $coverage['total'])
                : 0;

            $cards[] = [
                'id' => $role->value,
                'title' => (string) __('panel.consent_nav.segments.'.$role->value),
                'badge' => $missing > 0
                    ? (string) __('panel.consent_nav.missing_badge', ['count' => $missing])
                    : null,
                'percent' => $percent,
                'stats' => [
                    (string) __('panel.consent_nav.stat_confirmed', [
                        'confirmed' => $coverage['confirmed'],
                        'total' => $coverage['total'],
                        'percent' => $percent,
                    ]),
                    (string) trans_choice('panel.consent_nav.proofs_count', $coverage['proofs'], ['count' => $coverage['proofs']]),
                ],
            ];
        }

        return $cards;
    }

    /**
     * Pastilele contextului: Dovezi / De confirmat (cu restanța ca badge).
     *
     * @return array<int, array{key: string, label: string, count: int, active: bool}>
     */
    public function consentViewPills(): array
    {
        $role = $this->activeRole();

        if ($role === null) {
            return [];
        }

        $coverage = $this->coverage()[$role->value] ?? ['total' => 0, 'confirmed' => 0, 'proofs' => 0];

        return [
            [
                'key' => 'proofs',
                'label' => (string) __('panel.consent_nav.view_proofs'),
                'count' => $coverage['proofs'],
                'active' => ! $this->isMissingView(),
            ],
            [
                'key' => 'missing',
                'label' => (string) __('panel.consent_nav.view_missing'),
                'count' => max(0, $coverage['total'] - $coverage['confirmed']),
                'active' => $this->isMissingView(),
            ],
        ];
    }

    public function contextTitle(): string
    {
        $role = $this->activeRole();

        return $role === null ? '' : (string) __('panel.consent_nav.segments.'.$role->value);
    }

    /**
     * Conturile ACTIVE ale segmentului fără versiunea curentă confirmată (restanța), cu căutare.
     * Limităm afișarea (50) — restul se găsește prin căutare; numărul total rămâne vizibil.
     *
     * @return array{users: array<int, array{name: string, username: string|null, previous: string|null}>, total: int}
     */
    public function missingUsers(): array
    {
        $role = $this->activeRole();

        if ($role === null || ! $this->isMissingView()) {
            return ['users' => [], 'total' => 0];
        }

        $query = $this->missingQuery($role);
        $total = (clone $query)->count();

        $search = trim($this->missingSearch);

        $users = $query
            ->when($search !== '', function (Builder $inner) use ($search): void {
                $inner->where(function (Builder $where) use ($search): void {
                    $where->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['name', 'username', 'privacy_acknowledged_version'])
            ->map(fn (User $user): array => [
                'name' => (string) $user->name,
                'username' => $user->username,
                'previous' => $user->privacy_acknowledged_version,
            ])
            ->all();

        return ['users' => $users, 'total' => $total];
    }

    /**
     * Acoperirea per segment, o singură trecere per request: conturi ACTIVE (suspendarea nu
     * poate confirma), câte au versiunea CURENTĂ, câte dovezi există în istoric.
     *
     * @return array<string, array{total: int, confirmed: int, proofs: int}>
     */
    private function coverage(): array
    {
        if ($this->coverage !== null) {
            return $this->coverage;
        }

        $this->coverage = [];

        foreach (self::TARGET_ROLES as $role) {
            $accounts = User::query()
                ->whereNull('suspended_at')
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role->value));

            $this->coverage[$role->value] = [
                'total' => (clone $accounts)->count(),
                'confirmed' => (clone $accounts)
                    ->where('privacy_acknowledged_version', $this->currentVersion())
                    ->count(),
                'proofs' => ConsentAcknowledgment::query()
                    ->whereHas('user.roles', fn (Builder $roles) => $roles->where('name', $role->value))
                    ->count(),
            ];
        }

        return $this->coverage;
    }

    /**
     * @return Builder<User>
     */
    private function missingQuery(UserRole $role): Builder
    {
        return User::query()
            ->whereNull('suspended_at')
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role->value))
            ->where(function (Builder $query): void {
                $query->whereNull('privacy_acknowledged_version')
                    ->orWhere('privacy_acknowledged_version', '!=', $this->currentVersion());
            });
    }
}
