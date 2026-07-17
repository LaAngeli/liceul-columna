<?php

namespace App\Filament\Resources\DocumentRequests\Pages;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Models\DocumentRequest;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * „Cereri" (secretariat) = coada cererilor tipice ale familiilor (§4.3), pe limbajul
 * navigatoarelor panoului: vederi pe starea cererii („De procesat" / „Arhivă") → carduri pe
 * TIPUL cererii (învoire / adeverință / transfer / contestație / ședință — taxonomie fixă) →
 * tabelul în context. Starea trăiește în URL și e VALIDATĂ la citire.
 *
 * Coada exclude cererile elevilor ARHIVAȚI (nu blochează procesarea — aliniat cu badge-ul);
 * în arhivă ele rămân vizibile (istoricul complet).
 */
class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    protected string $view = 'filament.catalog.document-requests-navigator';

    /** Vederea „Arhivă" (cereri închise: aprobate/respinse); implicit = coada de procesat. */
    #[Url(as: 'arhiva', except: null)]
    public ?string $archiveMode = null;

    /** Tipul de cerere deschis (contextul): invoire / adeverinta / transfer / contestatie / sedinta. */
    #[Url(as: 'tip', except: null)]
    public ?string $activeType = null;

    /** @var array<string, mixed> */
    private array $navMemo = [];

    public function isArchiveView(): bool
    {
        return $this->archiveMode === '1';
    }

    public function setRequestsView(string $view): void
    {
        $this->archiveMode = $view === 'archive' ? '1' : null;
        $this->activeType = null;
        $this->navMemo = [];
        $this->resetTable();
    }

    /** Tipul activ — doar dacă e o valoare reală a enum-ului (URL-ul nu se ia de bun). */
    public function activeType(): ?DocumentRequestType
    {
        return $this->activeType === null ? null : DocumentRequestType::tryFrom($this->activeType);
    }

    public function openType(string $type): void
    {
        $this->activeType = DocumentRequestType::tryFrom($type)?->value;
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
     * @param  Builder<DocumentRequest>  $query
     * @return Builder<DocumentRequest>
     */
    public function applyRequestsContext(Builder $query): Builder
    {
        if ($this->isArchiveView()) {
            $query->whereIn('status', [RequestStatus::Approved->value, RequestStatus::Rejected->value]);
        } else {
            // Coada = doar cereri procesabile: elevii arhivați nu o blochează (ca badge-ul).
            $query->where('status', RequestStatus::Pending->value)
                ->whereHas('student', fn (Builder $student) => $student->whereNull('deleted_at'));
        }

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
    public function requestsViewPills(): array
    {
        return [
            [
                'key' => 'queue',
                'label' => (string) __('panel.document_nav.view_queue'),
                'count' => $this->queueRows()->count(),
                'active' => ! $this->isArchiveView(),
            ],
            [
                'key' => 'archive',
                'label' => (string) __('panel.document_nav.view_archive'),
                'count' => array_sum($this->archiveCounts()),
                'active' => $this->isArchiveView(),
            ],
        ];
    }

    public function requestsHint(): string
    {
        return (string) ($this->isArchiveView()
            ? __('panel.document_nav.archive_hint')
            : __('panel.document_nav.queue_hint'));
    }

    /**
     * Cardurile pe tipul cererii — taxonomie FIXĂ (toate cele 5, mereu): coada arată câte
     * așteaptă + cea mai veche; arhiva arată închiderile pe decizie.
     *
     * @return array<int, array{id: string, title: string, badge: string|null, stats: array<int, string>}>
     */
    public function typeCards(): array
    {
        $cards = [];

        foreach (DocumentRequestType::cases() as $type) {
            $cards[] = $this->isArchiveView() ? $this->archiveCard($type) : $this->queueCard($type);
        }

        return $cards;
    }

    public function queueIsEmpty(): bool
    {
        return ! $this->isArchiveView() && $this->queueRows()->isEmpty();
    }

    public function contextEyebrow(): string
    {
        return (string) ($this->isArchiveView()
            ? __('panel.document_nav.view_archive')
            : __('panel.document_nav.view_queue'));
    }

    public function contextTitle(): string
    {
        return (string) ($this->activeType()?->label() ?? '');
    }

    public function contextSubtitle(): ?string
    {
        $type = $this->activeType();

        if ($type === null) {
            return null;
        }

        $count = $this->isArchiveView()
            ? DocumentRequest::query()
                ->whereIn('status', [RequestStatus::Approved->value, RequestStatus::Rejected->value])
                ->where('type', $type->value)
                ->count()
            : $this->queueRows()->where('type', $type->value)->count();

        return (string) trans_choice('panel.document_nav.requests_count', $count, ['count' => $count]);
    }

    protected function getHeaderActions(): array
    {
        // Cererile se DEPUN din cabinet (familia); panoul doar le procesează.
        return [];
    }

    /**
     * @return array{id: string, title: string, badge: string|null, stats: array<int, string>}
     */
    private function queueCard(DocumentRequestType $type): array
    {
        $rows = $this->queueRows()->where('type', $type->value);
        $count = $rows->count();

        $stats = [
            (string) __('panel.document_nav.stat_pending', ['count' => $count]),
        ];

        $oldest = $rows->min('created_at');

        if ($oldest !== null) {
            $stats[] = (string) __('panel.document_nav.stat_oldest', [
                'time' => Carbon::parse((string) $oldest)->diffForHumans(),
            ]);
        }

        return [
            'id' => $type->value,
            'title' => $type->label(),
            'badge' => $count > 0 ? (string) $count : null,
            'stats' => $stats,
        ];
    }

    /**
     * @return array{id: string, title: string, badge: string|null, stats: array<int, string>}
     */
    private function archiveCard(DocumentRequestType $type): array
    {
        $counts = DocumentRequest::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->where('type', $type->value)
            ->whereIn('status', [RequestStatus::Approved->value, RequestStatus::Rejected->value])
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'id' => $type->value,
            'title' => $type->label(),
            'badge' => null,
            'stats' => [
                (string) __('panel.document_nav.stat_approved', ['count' => (int) $counts->get(RequestStatus::Approved->value, 0)]),
                (string) __('panel.document_nav.stat_rejected', ['count' => (int) $counts->get(RequestStatus::Rejected->value, 0)]),
            ],
        ];
    }

    /**
     * Rândurile PROCESABILE din coadă (fără elevii arhivați), o singură interogare per request —
     * pastila, cardurile și semnalele lor se calculează din aceeași colecție.
     *
     * @return Collection<int, \stdClass>
     */
    private function queueRows(): Collection
    {
        if (! array_key_exists('queue', $this->navMemo)) {
            $this->navMemo['queue'] = DocumentRequest::query()
                ->toBase()
                ->select(['document_requests.id', 'document_requests.type', 'document_requests.created_at'])
                ->join('students', 'students.id', '=', 'document_requests.student_id')
                ->whereNull('students.deleted_at')
                ->whereNull('document_requests.deleted_at')
                ->where('document_requests.status', RequestStatus::Pending->value)
                ->get();
        }

        return $this->navMemo['queue'];
    }

    /**
     * Numărătorile arhivei pe stări (pastila vederii).
     *
     * @return array<string, int>
     */
    private function archiveCounts(): array
    {
        if (! array_key_exists('archive', $this->navMemo)) {
            $this->navMemo['archive'] = DocumentRequest::query()
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->whereIn('status', [RequestStatus::Approved->value, RequestStatus::Rejected->value])
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $total): int => (int) $total)
                ->all();
        }

        return $this->navMemo['archive'];
    }
}
