<?php

namespace App\Filament\Resources\CorigentaExams\Pages;

use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Examenele de corigență, pe SESIUNEA lor (navigatorul de configurare, 2026-07-16): pastilele
 * sunt sesiunile (an + sezon) — axa naturală a examenelor — cu cea mai recentă implicită;
 * examenele rătăcite fără sesiune au bucket separat, doar când există. Refolosește view-ul
 * `config-year-table` (aceeași anatomie: pastile → tabel).
 */
class ListCorigentaExams extends ListRecords
{
    protected static string $resource = CorigentaExamResource::class;

    protected string $view = 'filament.catalog.config-year-table';

    /** Sesiunea activă (id „dorit" din URL, validat la citire; 0 = fără sesiune). */
    #[Url(as: 'sesiune', except: null)]
    public ?string $sessionParam = null;

    /** @var Collection<int|string, int>|null */
    private ?Collection $sessionCountsMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.exams_hint');
    }

    public function openYear(int|string $id): void
    {
        if ($this->sessionCounts()->has((int) $id)) {
            $this->sessionParam = (string) (int) $id;
        }
    }

    /** Sesiunea activă: cea cerută prin URL dacă există, altfel cea mai recentă cu examene. */
    public function activeYearId(): ?int
    {
        $counts = $this->sessionCounts();

        if ($this->sessionParam !== null && ctype_digit($this->sessionParam) && $counts->has((int) $this->sessionParam)) {
            return (int) $this->sessionParam;
        }

        $newest = $counts->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->sortDesc()
            ->first();

        return $newest ?? ($counts->has(0) ? 0 : null);
    }

    /**
     * Pastilele sesiunilor (cele mai recente întâi) + bucket-ul „Fără sesiune" când există.
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $counts = $this->sessionCounts();

        if ($counts->isEmpty()) {
            return [];
        }

        $sessions = CorigentaSession::query()
            ->with('academicYear')
            ->whereKey($counts->keys()->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->all())
            ->orderByDesc('starts_on')
            ->get();

        $pills = $sessions->map(fn (CorigentaSession $session): array => [
            'id' => (int) $session->id,
            'label' => trim(($session->academicYear->name ?? '').' · '.$session->season->label()),
            'count' => (int) ($counts->get($session->id) ?? 0),
        ])->all();

        if ($counts->has(0)) {
            $pills[] = [
                'id' => 0,
                'label' => (string) __('panel.config_nav.no_session'),
                'count' => (int) $counts->get(0),
            ];
        }

        return $pills;
    }

    /**
     * Constrângerea tabelului pe sesiunea activă (apelată din CorigentaExamsTable).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyYearContext(Builder $query): Builder
    {
        $sessionId = $this->activeYearId();

        if ($sessionId === null) {
            return $query;
        }

        return $sessionId === 0
            ? $query->whereNull('corigenta_session_id')
            : $query->where('corigenta_session_id', $sessionId);
    }

    /** @return Collection<int|string, int> examene per sesiune (0 = fără sesiune) */
    private function sessionCounts(): Collection
    {
        return $this->sessionCountsMemo ??= CorigentaExam::query()
            ->toBase()
            ->selectRaw('COALESCE(corigenta_session_id, 0) AS session_id, COUNT(*) AS aggregate')
            ->groupBy('session_id')
            ->pluck('aggregate', 'session_id')
            ->map(fn ($count): int => (int) $count);
    }
}
