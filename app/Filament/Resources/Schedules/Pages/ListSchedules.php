<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Enums\ScheduleType;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Support\ScheduleCoverage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Orarele publicabile, pe cele 9 TIPURI din Calendar (navigatorul de configurare, 2026-07-16):
 * un card per tip — câte tabele are, câte sunt publice, avertisment „neconfigurat" pentru tipurile
 * fără niciun tabel PUBLICAT. Definiția e unică ({@see ScheduleCoverage}) și o
 * împart widget-ul SchedulesToComplete și hub-ul de configurare; înainte, acest navigator marca
 * „fără date" doar tipurile fără NICIUN rând, deci un orar scris-dar-nepublicat apărea complet
 * aici și lipsă în widget, iar docblock-ul le declara — fals — identice.
 */
class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected string $view = 'filament.catalog.schedules-navigator';

    /** Tipul deschis (slug „dorit" din URL, validat la citire prin enum). */
    #[Url(as: 'tip', except: null)]
    public ?string $typeParam = null;

    /** @var Collection<string, \stdClass>|null */
    private ?Collection $typeCountsMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function openType(string $value): void
    {
        if (ScheduleType::tryFrom($value) !== null) {
            $this->typeParam = $value;
        }
    }

    public function leaveType(): void
    {
        $this->typeParam = null;
    }

    public function activeType(): ?ScheduleType
    {
        return $this->typeParam !== null ? ScheduleType::tryFrom($this->typeParam) : null;
    }

    /** Gestiunea orarului (AO + super-admin, pe rolul ACTIV) — restul rolurilor CONSULTĂ. */
    public function isManager(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    /**
     * Constrângerea tabelului pe tipul activ + perimetrul de vizibilitate pe rol (apelată din
     * SchedulesTable): cititorii văd DOAR tabelele PUBLICATE — ciornele sunt atelierul AO-ului,
     * nu informație oficială; ce apare aici pentru un profesor coincide cu ce vede site-ul.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTypeContext(Builder $query): Builder
    {
        $type = $this->activeType();

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        if (! $this->isManager()) {
            $query->where('is_public', true);
        }

        return $query;
    }

    /**
     * Cardurile celor 9 tipuri, pe ROL:
     *  - gestionar (AO/super): tabele totale + câte publice; „fără date" = avertisment de LUCRU
     *    (obligația de completare, spec §3.2) — badge roșu;
     *  - cititor: DOAR numărul tabelelor publicate (ciornele nu există pentru el); tipul gol
     *    poartă un badge neutru „fără orar publicat" — stare, nu sarcină.
     *
     * @return array<int, array{id: string, title: string, stats: array<int, string>, badge: string|null, badge_color: string}>
     */
    public function typeCards(): array
    {
        $counts = $this->typeCounts();
        $manager = $this->isManager();

        $cards = [];

        foreach (ScheduleType::cases() as $type) {
            $row = $counts->get($type->value);
            $total = (int) ($row->aggregate ?? 0);
            $public = (int) ($row->public_count ?? 0);

            if ($manager) {
                $stats = $total > 0
                    ? [
                        (string) trans_choice('panel.config_nav.schedule_tables', $total, ['count' => $total]),
                        (string) __('panel.config_nav.schedule_public', ['count' => $public]),
                    ]
                    : [(string) __('panel.config_nav.schedule_empty')];

                // „Neconfigurat" = fără tabel PUBLICAT (nu fără niciun rând): un orar scris și
                // nepublicat nu ajunge la nimeni, deci sarcina AO nu e încheiată.
                $badge = $public === 0 ? (string) __('panel.config_nav.schedule_missing') : null;
                $badgeColor = 'danger';
            } else {
                $stats = [(string) trans_choice('panel.config_nav.schedule_public_tables', $public, ['count' => $public])];
                $badge = $public === 0 ? (string) __('panel.config_nav.schedule_none_public') : null;
                $badgeColor = 'gray';
            }

            $cards[] = [
                'id' => $type->value,
                'title' => $type->label(),
                'stats' => $stats,
                'badge' => $badge,
                'badge_color' => $badgeColor,
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __($this->isManager()
            ? 'panel.config_nav.schedules_hint'
            : 'panel.config_nav.schedules_hint_reader');
    }

    /**
     * Starea tipului activ, pentru antetul contextului: câte tabele, câte publice.
     *
     * @return array{total: int, public: int}|null
     */
    public function typeStats(): ?array
    {
        $type = $this->activeType();

        if ($type === null) {
            return null;
        }

        $row = $this->typeCounts()->get($type->value);

        return [
            'total' => (int) ($row->aggregate ?? 0),
            'public' => (int) ($row->public_count ?? 0),
        ];
    }

    /** @return Collection<string, \stdClass> */
    private function typeCounts(): Collection
    {
        return $this->typeCountsMemo ??= Schedule::query()
            ->toBase()
            ->selectRaw('type, COUNT(*) AS aggregate, SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) AS public_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');
    }
}
