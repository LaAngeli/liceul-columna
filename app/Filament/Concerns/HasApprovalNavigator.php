<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Navigator pentru cozile de APROBARE (Corecții note / Corecții teme / Motivări absențe):
 * vederi pe STAREA cererii — „De procesat" (implicit) și „Arhivă" — apoi carduri pe entitatea
 * potrivită secției (solicitant / clasă), cu tabelul în context. Cardurile apar DOAR pentru
 * entitățile cu cereri în vederea activă (coada rămâne scurtă și acționabilă).
 *
 * Solicitantul (fără drept de procesare) primește tabelul plat cu cererile PROPRII —
 * navigatorul e unealta celui care judecă cererile.
 *
 * Pagina care îl folosește declară proprietatea `$targetParam` cu alias-ul ei de URL
 * (`?solicitant=` / `?clasa=`) — trait-ul îi gestionează validarea la citire prin carduri.
 */
trait HasApprovalNavigator
{
    /** Vederea activă: coada „De procesat" (implicit) sau arhiva cererilor judecate. */
    #[Url(as: 'vedere', except: null)]
    public ?string $viewParam = null;

    /** @var array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}>|null */
    private ?array $approvalCardsMemo = null;

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function isArchiveView(): bool
    {
        return $this->viewParam === 'arhiva';
    }

    public function setApprovalView(string $key): void
    {
        // Entitatea aleasă nu se transferă între vederi (seturile de carduri diferă).
        $this->viewParam = $key === 'arhiva' ? 'arhiva' : null;
        $this->targetParam = null;
        $this->approvalCardsMemo = null;
    }

    public function openTarget(int|string $id): void
    {
        $id = (int) $id;

        if ($this->approvalCardHasId($id)) {
            $this->targetParam = (string) $id;
        }
    }

    public function leaveTarget(): void
    {
        $this->targetParam = null;
    }

    /** Ținta activă: id-ul cerut prin URL, validat prin cardurile vederii; altfel fallback-ul. */
    public function activeTargetId(): ?int
    {
        if ($this->targetParam !== null && ctype_digit($this->targetParam) && $this->approvalCardHasId((int) $this->targetParam)) {
            return (int) $this->targetParam;
        }

        return $this->fallbackTargetId();
    }

    /** Contextul deschis automat (fără parametru explicit) — fără buton „înapoi". */
    public function isFallbackTarget(): bool
    {
        return ($this->targetParam === null || ! ctype_digit($this->targetParam) || ! $this->approvalCardHasId((int) $this->targetParam))
            && $this->fallbackTargetId() !== null;
    }

    /**
     * Cu un singur card în vedere, secțiile pot deschide contextul direct (dirigintele cu o
     * singură clasă nu are ce alege). Implicit: dezactivat.
     */
    protected function fallbackTargetId(): ?int
    {
        return null;
    }

    // ── Vederi (pastile) + carduri ──────────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string, count: int, active: bool}> */
    public function approvalViewPills(): array
    {
        return [
            [
                'key' => 'asteptare',
                'label' => (string) __('panel.approval_nav.queue'),
                'count' => $this->approvalCount(archive: false),
                'active' => ! $this->isArchiveView(),
            ],
            [
                'key' => 'arhiva',
                'label' => (string) __('panel.approval_nav.archive'),
                'count' => $this->approvalCount(archive: true),
                'active' => $this->isArchiveView(),
            ],
        ];
    }

    /** @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}> */
    public function approvalCards(): array
    {
        return $this->approvalCardsMemo ??= $this->buildApprovalCards();
    }

    public function approvalContextTitle(): string
    {
        $id = $this->activeTargetId();

        foreach ($this->approvalCards() as $card) {
            if ($card['id'] === $id) {
                return $card['title'];
            }
        }

        return '';
    }

    public function approvalContextSubtitle(): ?string
    {
        $id = $this->activeTargetId();

        foreach ($this->approvalCards() as $card) {
            if ($card['id'] === $id) {
                return $card['subtitle'];
            }
        }

        return null;
    }

    // ── Constrângerea tabelului ─────────────────────────────────────────────────────────────

    /**
     * Aplicată din tabelul resursei: vederea (starea cererii) + ținta activă. Pentru solicitant
     * (fără navigator) tabelul rămâne neatins — cererile lui, toate stările.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyApprovalContext(Builder $query): Builder
    {
        if (! $this->isQueueManagerView()) {
            return $query;
        }

        $this->isArchiveView() ? $this->constrainToArchive($query) : $this->constrainToQueue($query);

        $target = $this->activeTargetId();

        if ($target !== null) {
            $this->constrainToTarget($query, $target);
        }

        return $query;
    }

    /**
     * Vederea implicită: cererile în așteptare. Suprascriptibilă (Motivările exclud și elevii
     * arhivați, aliniat cu badge-ul de sidebar).
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToQueue(Builder $query): void
    {
        $query->where('status', $this->pendingStatusValue());
    }

    /**
     * Arhiva: tot ce NU mai e în așteptare (aprobat/respins/expirat/retras).
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToArchive(Builder $query): void
    {
        $query->where('status', '!=', $this->pendingStatusValue());
    }

    // ── Utilitare interne ───────────────────────────────────────────────────────────────────

    private function approvalCardHasId(int $id): bool
    {
        foreach ($this->approvalCards() as $card) {
            if ($card['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    private function approvalCount(bool $archive): int
    {
        $query = $this->approvalBaseQuery();

        $archive ? $this->constrainToArchive($query) : $this->constrainToQueue($query);

        return $query->count();
    }

    /**
     * Cardurile pe SOLICITANT (corecțiile de note/teme): câte cereri are fiecare în vederea
     * activă + reperul de triaj (cea mai veche din coadă / ultima din arhivă).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}>
     */
    protected function buildRequesterCards(): array
    {
        $query = $this->approvalBaseQuery();

        $this->isArchiveView() ? $this->constrainToArchive($query) : $this->constrainToQueue($query);

        $rows = $query->toBase()
            ->selectRaw('requested_by_user_id, COUNT(*) AS total, MIN(created_at) AS oldest, MAX(created_at) AS latest')
            ->groupBy('requested_by_user_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereKey($rows->pluck('requested_by_user_id')->filter()->all())
            ->pluck('name', 'id');

        $cards = [];

        foreach ($rows as $row) {
            $date = $this->isArchiveView() ? $row->latest : $row->oldest;

            $stats = [(string) trans_choice('panel.approval_nav.requests', (int) $row->total, ['count' => (int) $row->total])];

            if ($date !== null) {
                $stats[] = (string) __($this->isArchiveView() ? 'panel.catalog_nav.last_record' : 'panel.approval_nav.oldest', [
                    'date' => Carbon::parse((string) $date)->format('d.m.Y'),
                ]);
            }

            $cards[] = [
                'id' => (int) $row->requested_by_user_id,
                'title' => (string) ($names->get($row->requested_by_user_id) ?? __('panel.common.system')),
                'subtitle' => null,
                'stats' => $stats,
                'badge' => null,
            ];
        }

        usort($cards, fn (array $a, array $b): int => strcoll($a['title'], $b['title']));

        return $cards;
    }

    // ── Hook-uri per secție ─────────────────────────────────────────────────────────────────

    /** Primește utilizatorul curent navigatorul (procesator/arhivar) sau tabelul plat propriu? */
    abstract public function isQueueManagerView(): bool;

    /** Ghidul de sub titlu (textul diferă pe secție). */
    abstract public function approvalHint(): string;

    /** Eticheta-etaj a contextului („Solicitant" / „Clasa"). */
    abstract public function approvalContextEyebrow(): string;

    /** Valoarea „în așteptare" a enum-ului de stare al secției. */
    abstract protected function pendingStatusValue(): string;

    /**
     * Interogarea de bază (query-ul SCOPED al resursei — perimetrul rămâne definit acolo).
     *
     * @return Builder<Model>
     */
    abstract protected function approvalBaseQuery(): Builder;

    /** @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}> */
    abstract protected function buildApprovalCards(): array;

    /**
     * @param  Builder<Model>  $query
     */
    abstract protected function constrainToTarget(Builder $query, int $targetId): void;
}
