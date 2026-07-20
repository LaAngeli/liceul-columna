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

    /**
     * Constrângerea tabelului pe tipul activ (apelată din SchedulesTable).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTypeContext(Builder $query): Builder
    {
        $type = $this->activeType();

        return $type !== null
            ? $query->where('type', $type->value)
            : $query;
    }

    /**
     * Cardurile celor 9 tipuri: numărul de tabele + câte sunt publice; „fără date" = avertisment
     * (obligația de completare e a administratorului operațional, spec §3.2).
     *
     * @return array<int, array{id: string, title: string, stats: array<int, string>, badge: string|null}>
     */
    public function typeCards(): array
    {
        $counts = $this->typeCounts();

        $cards = [];

        foreach (ScheduleType::cases() as $type) {
            $row = $counts->get($type->value);
            $total = (int) ($row->aggregate ?? 0);
            $public = (int) ($row->public_count ?? 0);

            $stats = $total > 0
                ? [
                    (string) trans_choice('panel.config_nav.schedule_tables', $total, ['count' => $total]),
                    (string) __('panel.config_nav.schedule_public', ['count' => $public]),
                ]
                : [(string) __('panel.config_nav.schedule_empty')];

            $cards[] = [
                'id' => $type->value,
                'title' => $type->label(),
                'stats' => $stats,
                // „Neconfigurat" = fără tabel PUBLICAT (nu fără niciun rând): un orar scris și
                // nepublicat nu ajunge la nimeni, deci sarcina AO nu e încheiată.
                'badge' => $public === 0 ? (string) __('panel.config_nav.schedule_missing') : null,
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.schedules_hint');
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
