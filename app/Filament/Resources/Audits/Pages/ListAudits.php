<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use App\Support\AuditCategories;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Jurnalul de audit SECȚIONAT pe categorii de date (cerința beneficiarului): aterizarea =
 * carduri pe categorie (Catalog & evaluare / Dosarele elevilor / Conturi / Admitere & cereri /
 * Conținut & calendar, + „Altele" doar când există tipuri neîncadrate) cu numărători și pulsul
 * activității (azi / 7 zile) → tabelul în contextul categoriei.
 *
 * Numărătorile se calculează din interogarea SCOPATĂ a resursei — administratorul tehnic își
 * păstrează minimizarea (nu vede datele academice nici în carduri, nici în tabel).
 */
class ListAudits extends ListRecords
{
    protected static string $resource = AuditResource::class;

    protected string $view = 'filament.catalog.audits-navigator';

    /** Categoria deschisă (contextul); validată la citire, nu la scriere. */
    #[Url(as: 'categorie', except: null)]
    public ?string $activeCategory = null;

    /** @var Collection<int, \stdClass>|null */
    private ?Collection $typeCounts = null;

    public function activeCategory(): ?string
    {
        return $this->activeCategory !== null && AuditCategories::isValid($this->activeCategory)
            ? $this->activeCategory
            : null;
    }

    public function openCategory(string $key): void
    {
        $this->activeCategory = AuditCategories::isValid($key) ? $key : null;
        $this->resetTable();
    }

    public function leaveCategory(): void
    {
        $this->activeCategory = null;
        $this->resetTable();
    }

    /**
     * Constrângerea contextului pe interogarea tabelului (peste scoping-ul resursei).
     *
     * @param  Builder<Audit>  $query
     * @return Builder<Audit>
     */
    public function applyAuditContext(Builder $query): Builder
    {
        $category = $this->activeCategory();

        return $category === null ? $query : AuditCategories::applyTo($query, $category);
    }

    public function auditHint(): string
    {
        return (string) __('panel.audit_nav.hint');
    }

    /**
     * Cardurile categoriilor: cele 5 fixe mereu (numărători din query-ul scopat) + „Altele"
     * doar când există intrări neîncadrate.
     *
     * @return array<int, array{id: string, title: string, description: string, badge: string|null, stats: array<int, string>}>
     */
    public function categoryCards(): array
    {
        $buckets = $this->bucketedCounts();
        $cards = [];

        foreach ([...AuditCategories::keys(), AuditCategories::OTHER] as $key) {
            $bucket = $buckets[$key] ?? ['total' => 0, 'today' => 0, 'week' => 0];

            if ($key === AuditCategories::OTHER && $bucket['total'] === 0) {
                continue;
            }

            $cards[] = [
                'id' => $key,
                'title' => AuditCategories::label($key),
                'description' => AuditCategories::description($key),
                'badge' => $bucket['today'] > 0
                    ? (string) __('panel.audit_nav.stat_today', ['count' => $bucket['today']])
                    : null,
                'stats' => [
                    (string) trans_choice('panel.audit_nav.entries_count', $bucket['total'], ['count' => $bucket['total']]),
                    (string) __('panel.audit_nav.stat_week', ['count' => $bucket['week']]),
                ],
            ];
        }

        return $cards;
    }

    public function contextTitle(): string
    {
        $category = $this->activeCategory();

        return $category === null ? '' : AuditCategories::label($category);
    }

    public function contextSubtitle(): ?string
    {
        $category = $this->activeCategory();

        if ($category === null) {
            return null;
        }

        $total = ($this->bucketedCounts()[$category] ?? ['total' => 0])['total'];

        return (string) trans_choice('panel.audit_nav.entries_count', $total, ['count' => $total]);
    }

    /**
     * Numărătorile pe tip → agregate pe categorie, o singură interogare per request, din
     * query-ul SCOPAT al resursei (minimizarea AT se aplică și cardurilor).
     *
     * @return array<string, array{total: int, today: int, week: int}>
     */
    private function bucketedCounts(): array
    {
        if ($this->typeCounts === null) {
            $this->typeCounts = AuditResource::getEloquentQuery()
                ->toBase()
                ->selectRaw(
                    'auditable_type, count(*) as total,'
                    .' sum(case when created_at >= ? then 1 else 0 end) as today,'
                    .' sum(case when created_at >= ? then 1 else 0 end) as week',
                    [now()->startOfDay(), now()->subDays(7)],
                )
                ->groupBy('auditable_type')
                ->get();
        }

        $buckets = [];

        foreach ($this->typeCounts as $row) {
            $key = AuditCategories::categoryOf((string) $row->auditable_type);

            $bucket = $buckets[$key] ?? ['total' => 0, 'today' => 0, 'week' => 0];
            $bucket['total'] += (int) $row->total;
            $bucket['today'] += (int) $row->today;
            $bucket['week'] += (int) $row->week;
            $buckets[$key] = $bucket;
        }

        return $buckets;
    }
}
