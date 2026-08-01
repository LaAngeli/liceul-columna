<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use App\Models\CanteenMenu;
use App\Support\CanteenWeek;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Planificatorul săptămânal al meniului (v2, după feedback: tabelul-listă era incomod și
 * neintuitiv). Meniul se gândește pe săptămâni, deci exact așa se și afișează: grila zilelor cu
 * rubricile fiecăreia, azi evidențiat, navigare între săptămâni. Administratorul operațional
 * lucrează direct pe zi — „Adaugă"/„Modifică" pe cardul ei, cu data DEJA aleasă (nu mai există
 * pasul „alege data" dintr-un formular gol) și „Preia de săptămâna trecută" acolo unde există
 * sursă (meniurile se repetă ciclic). Cititorii văd aceeași grilă, fără nicio comandă.
 */
class CanteenMenuPlanner extends Page
{
    protected static string $resource = CanteenMenuResource::class;

    protected string $view = 'filament.canteen.week-planner';

    /** Orice zi din săptămâna dorită; lipsă/coruptă → săptămâna curentă. */
    #[Url(as: 'saptamana', except: null)]
    public ?string $weekParam = null;

    /** @var array<string, mixed>|null */
    private ?array $weekMemo = null;

    public function getTitle(): string
    {
        return __('panel.resources.canteen_menus.label');
    }

    /**
     * Butonul global de adăugare — EXPLICIT doar pentru cine gestionează meniul: acțiunile din
     * antet nu trec singure prin gărzile resursei, iar fără `visible()` directorul vedea un buton
     * care ducea la 403 (raportat 2026-08-01).
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('panel.forms.canteen.add_header'))
                ->icon('heroicon-o-plus')
                ->url(fn (): string => CanteenMenuResource::getUrl('create'))
                ->visible(fn (): bool => self::canManage()),
        ];
    }

    public static function canManage(): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    /**
     * Săptămâna afișată (memoizată pe request — blade-ul o citește de mai multe ori).
     *
     * @return array<string, mixed>
     */
    public function week(): array
    {
        return $this->weekMemo ??= CanteenWeek::build($this->weekParam);
    }

    /** URL-ul planificatorului ancorat pe altă săptămână. */
    public function weekUrl(string $anchor): string
    {
        return CanteenMenuResource::getUrl(parameters: ['saptamana' => $anchor]);
    }

    /** URL-ul de creare pentru o zi anume, opțional pre-completat dintr-o zi-sursă. */
    public function createUrl(string $date, ?int $sourceId = null): string
    {
        return CanteenMenuResource::getUrl('create', array_filter([
            'data' => $date,
            'sursa' => $sourceId,
        ]));
    }

    public function editUrl(CanteenMenu $menu): string
    {
        return CanteenMenuResource::getUrl('edit', ['record' => $menu]);
    }

    /**
     * Sursa ofertei „Preia de săptămâna trecută" pentru o zi goală: meniul din ACEEAȘI zi a
     * săptămânii precedente (ritmul real al cantinei e ciclul săptămânal). Nimic → fără ofertă.
     */
    public function previousWeekMenu(string $date): ?CanteenMenu
    {
        return CanteenMenu::query()
            ->whereDate('menu_date', Carbon::parse($date)->subWeek()->toDateString())
            ->first();
    }
}
