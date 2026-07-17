<?php

namespace App\Filament\Resources\AdmissionRequests\Pages;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use App\Models\AdmissionRequest;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * „Cereri de înscriere" = coada de intake a admiterii, pe limbajul navigatoarelor panoului:
 * vederi pe starea cererii („De procesat" / „Arhivă") → carduri pe TIPUL cererii (programări
 * de vizită / cereri de înmatriculare — taxonomie fixă, ca la Orare) → tabelul în context.
 * Starea trăiește în URL și e VALIDATĂ la citire, nu la scriere.
 */
class ListAdmissionRequests extends ListRecords
{
    protected static string $resource = AdmissionRequestResource::class;

    protected string $view = 'filament.catalog.admissions-navigator';

    /** Vederea „Arhivă" (cereri închise: înmatriculat/refuzat); implicit = coada de procesat. */
    #[Url(as: 'arhiva', except: null)]
    public ?string $archiveMode = null;

    /** Tipul de cerere deschis (contextul): visit / enrollment. */
    #[Url(as: 'tip', except: null)]
    public ?string $activeType = null;

    /** @var array<string, mixed> */
    private array $navMemo = [];

    public function isArchiveView(): bool
    {
        return $this->archiveMode === '1';
    }

    public function setAdmissionView(string $view): void
    {
        $this->archiveMode = $view === 'archive' ? '1' : null;
        $this->activeType = null;
        $this->navMemo = [];
        $this->resetTable();
    }

    /** Tipul activ — doar dacă e o valoare reală a enum-ului (URL-ul nu se ia de bun). */
    public function activeType(): ?AdmissionRequestType
    {
        return $this->activeType === null ? null : AdmissionRequestType::tryFrom($this->activeType);
    }

    public function openType(string $type): void
    {
        $this->activeType = AdmissionRequestType::tryFrom($type)?->value;
        $this->resetTable();
    }

    public function leaveType(): void
    {
        $this->activeType = null;
        $this->resetTable();
    }

    /**
     * Constrângerea contextului pe interogarea tabelului: vederea (coadă/arhivă) + tipul.
     *
     * @param  Builder<AdmissionRequest>  $query
     * @return Builder<AdmissionRequest>
     */
    public function applyAdmissionContext(Builder $query): Builder
    {
        $query->whereIn('status', $this->isArchiveView() ? AdmissionStatus::finalValues() : AdmissionStatus::pendingValues());

        $type = $this->activeType();

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query;
    }

    /**
     * Pastilele vederilor (De procesat / Arhivă), cu numărători.
     *
     * @return array<int, array{key: string, label: string, count: int, active: bool}>
     */
    public function admissionViewPills(): array
    {
        $counts = $this->statusCounts();

        $pending = array_sum(array_intersect_key($counts, array_flip(AdmissionStatus::pendingValues())));
        $archived = array_sum(array_intersect_key($counts, array_flip(AdmissionStatus::finalValues())));

        return [
            [
                'key' => 'queue',
                'label' => (string) __('panel.admission_nav.view_queue'),
                'count' => $pending,
                'active' => ! $this->isArchiveView(),
            ],
            [
                'key' => 'archive',
                'label' => (string) __('panel.admission_nav.view_archive'),
                'count' => $archived,
                'active' => $this->isArchiveView(),
            ],
        ];
    }

    public function admissionHint(): string
    {
        return (string) ($this->isArchiveView()
            ? __('panel.admission_nav.archive_hint')
            : __('panel.admission_nav.queue_hint'));
    }

    /**
     * Cardurile pe tipul cererii — taxonomie FIXĂ (ambele tipuri, mereu), cu numărători și
     * semnale specifice vederii: coada arată noi/contactate + cea mai veche în așteptare și
     * vizitele viitoare; arhiva arată închiderile pe decizie.
     *
     * @return array<int, array{id: string, title: string, badge: string|null, stats: array<int, string>}>
     */
    public function typeCards(): array
    {
        $cards = [];

        foreach (AdmissionRequestType::cases() as $type) {
            $cards[] = $this->isArchiveView() ? $this->archiveCard($type) : $this->queueCard($type);
        }

        return $cards;
    }

    /** Coada e goală (ambele tipuri la zero) → stare de „totul procesat". */
    public function queueIsEmpty(): bool
    {
        return ! $this->isArchiveView()
            && array_sum(array_intersect_key($this->statusCounts(), array_flip(AdmissionStatus::pendingValues()))) === 0;
    }

    public function contextEyebrow(): string
    {
        return (string) ($this->isArchiveView()
            ? __('panel.admission_nav.view_archive')
            : __('panel.admission_nav.view_queue'));
    }

    public function contextTitle(): string
    {
        $type = $this->activeType();

        return (string) ($type === null ? '' : __('panel.admission_nav.types.'.$type->value));
    }

    public function contextSubtitle(): ?string
    {
        $type = $this->activeType();

        if ($type === null) {
            return null;
        }

        $count = AdmissionRequest::query()
            ->where('type', $type->value)
            ->whereIn('status', $this->isArchiveView() ? AdmissionStatus::finalValues() : AdmissionStatus::pendingValues())
            ->count();

        return (string) trans_choice('panel.admission_nav.requests_count', $count, ['count' => $count]);
    }

    protected function getHeaderActions(): array
    {
        // Cererile se nasc DOAR pe site-ul public (formularul familiei) — panoul le procesează.
        return [];
    }

    /**
     * @return array{id: string, title: string, badge: string|null, stats: array<int, string>}
     */
    private function queueCard(AdmissionRequestType $type): array
    {
        $rows = $this->pendingRows()->where('type', $type->value);

        $new = $rows->where('status', AdmissionStatus::Nou->value)->count();
        $contacted = $rows->where('status', AdmissionStatus::Contactat->value)->count();

        $stats = [
            (string) __('panel.admission_nav.stat_new', ['count' => $new]),
            (string) __('panel.admission_nav.stat_contacted', ['count' => $contacted]),
        ];

        $oldest = $rows->min('created_at');

        if ($oldest !== null) {
            $stats[] = (string) __('panel.admission_nav.stat_oldest', [
                'time' => Carbon::parse((string) $oldest)->diffForHumans(),
            ]);
        }

        if ($type === AdmissionRequestType::Visit) {
            $upcoming = $rows
                ->filter(function (\stdClass $row): bool {
                    try {
                        return $row->preferred_time !== null
                            && Carbon::parse((string) $row->preferred_time)->isFuture();
                    } catch (\Throwable) {
                        return false;
                    }
                })
                ->count();

            $stats[] = (string) __('panel.admission_nav.stat_upcoming_visits', ['count' => $upcoming]);
        }

        return [
            'id' => $type->value,
            'title' => (string) __('panel.admission_nav.types.'.$type->value),
            'badge' => $new > 0 ? (string) $new : null,
            'stats' => $stats,
        ];
    }

    /**
     * @return array{id: string, title: string, badge: string|null, stats: array<int, string>}
     */
    private function archiveCard(AdmissionRequestType $type): array
    {
        $counts = AdmissionRequest::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->where('type', $type->value)
            ->whereIn('status', AdmissionStatus::finalValues())
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'id' => $type->value,
            'title' => (string) __('panel.admission_nav.types.'.$type->value),
            'badge' => null,
            'stats' => [
                (string) __('panel.admission_nav.stat_enrolled', ['count' => (int) $counts->get(AdmissionStatus::Inmatriculat->value, 0)]),
                (string) __('panel.admission_nav.stat_refused', ['count' => (int) $counts->get(AdmissionStatus::Refuzat->value, 0)]),
            ],
        ];
    }

    /**
     * Rândurile în lucru, o singură interogare per request (cardurile ambelor tipuri +
     * semnalele lor se calculează din aceeași colecție).
     *
     * @return Collection<int, \stdClass>
     */
    private function pendingRows(): Collection
    {
        if (! array_key_exists('pending', $this->navMemo)) {
            $this->navMemo['pending'] = AdmissionRequest::query()
                ->toBase()
                ->select(['id', 'type', 'status', 'created_at', 'preferred_time'])
                ->whereIn('status', AdmissionStatus::pendingValues())
                ->get();
        }

        return $this->navMemo['pending'];
    }

    /**
     * Numărătorile pe stări, o singură interogare (pastilele vederilor + starea de coadă goală).
     *
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        if (! array_key_exists('statuses', $this->navMemo)) {
            $this->navMemo['statuses'] = AdmissionRequest::query()
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $total): int => (int) $total)
                ->all();
        }

        return $this->navMemo['statuses'];
    }
}
