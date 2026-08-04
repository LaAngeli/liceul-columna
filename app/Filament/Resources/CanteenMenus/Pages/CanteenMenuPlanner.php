<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use App\Models\CanteenMenu;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\CanteenWeek;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Carbon;

/**
 * Planificatorul meniului (v2, după feedback: tabelul-listă era incomod și neintuitiv), cu AXA
 * TEMPORALĂ standard a panoului (Toate / Zi / Săptămână / Lună / Personalizat — aceeași bară ca la
 * Teme/Note/Absențe, cerința 2026-08-01). Semantica pe moduri e a MENIULUI:
 *   • Zi / Săptămână / Lună = PLANIFICARE — grila completă, cu zilele goale și comenzile lor
 *     („Adaugă"/„Preia de săptămâna trecută"), fiindcă acolo se lucrează; implicit = Săptămâna.
 *   • Toate / Personalizat = CONSULTARE — doar zilele CU meniu, grupate pe săptămâni, recente
 *     întâi; un interval liber sau întreaga arhivă n-au zile goale de umplut.
 * Cititorii văd aceleași vederi, fără nicio comandă.
 */
class CanteenMenuPlanner extends Page
{
    use HasTimeNavigator;

    protected static string $resource = CanteenMenuResource::class;

    protected string $view = 'filament.canteen.week-planner';

    public function getTitle(): string
    {
        return __('panel.resources.canteen_menus.label');
    }

    /**
     * Clasa de pagină pe care se agață regula responsivă a antetului (stilurile în
     * {@see AdminPanelProvider}): pe telefon, „Adăugare meniu" stă ÎN
     * DREPTUL titlului, nu pe rândul de sub el — cerința 2026-08-01. Scoped la pagina asta,
     * deliberat: paginile cu titluri lungi sau cu mai multe acțiuni în antet au nevoie de
     * stivuirea implicită a Filament, iar o regulă globală le-ar înghesui.
     *
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-canteen-planner'];
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
            // Arhiva lunilor încheiate — de când publicarea e amânată la 25, luna trecută dispare
            // din planificator, deci trebuie să existe un loc de unde se poate recupera.
            Action::make('history')
                ->label(__('panel.forms.canteen.history_action'))
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url(fn (): string => CanteenMenuResource::getUrl('history'))
                ->visible(fn (): bool => self::canManage()),
        ];
    }

    public static function canManage(): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    // ── Axa temporală, adaptată paginii fără tabel ─────────────────────────────────────────────

    /**
     * Modul implicit al MENIULUI e Săptămâna — acolo se planifică.
     *
     * Pagina își ținea până acum propriile copii ale lui `timeMode()`/`setTimeMode()`, cu lista de
     * moduri scrisă a doua oară și cu un `CarbonImmutable::today()` rămas pe ora SERVERULUI — exact
     * greșeala de fus orar găsită pe această pagină și corectată în trait. Regula a urcat acolo
     * ({@see HasTimeNavigator::defaultTimeMode()}), aici rămâne doar alegerea.
     */
    protected function defaultTimeMode(): ?string
    {
        return 'saptamana';
    }

    /** Cerut de trait pentru filtrarea de tabel — pagina n-are tabel, dar contractul rămâne. */
    protected function timeDateExpression(): string|Expression
    {
        return 'menu_date';
    }

    /** Trait-ul anunță tabelul după fiecare schimbare; planificatorul n-are ce reseta. */
    public function resetTable(): void
    {
        // Fără tabel — re-randarea Livewire e tot ce trebuie.
    }

    // ── Datele vederilor ───────────────────────────────────────────────────────────────────────

    /**
     * Vederea de PLANIFICARE (Zi / Săptămâna / Luna): săptămâni complete, cu zilele goale.
     * `null` când modul activ e unul de consultare.
     *
     * @return array<int, array{label: string|null, days: array<int, array<string, mixed>>}>|null
     */
    public function planningWeeks(): ?array
    {
        $period = $this->period();

        return $period['kind'] === 'planning' ? $period['groups'] : null;
    }

    /**
     * Vederea de CONSULTARE (Toate / Personalizat): doar zilele cu meniu, pe săptămâni, recente
     * întâi. `null` când modul activ e unul de planificare.
     *
     * @return array<int, array{label: string, days: array<int, array<string, mixed>>}>|null
     */
    public function archiveWeeks(): ?array
    {
        $period = $this->period();

        return $period['kind'] === 'archive' ? $period['groups'] : null;
    }

    /**
     * Perioada activă, prin sursa UNICĂ ({@see CanteenWeek::period()}) — aceeași semantică a
     * modurilor pe care o folosește și pagina din cabinet.
     *
     * @return array{kind: 'planning'|'archive', groups: array<int, array{label: string|null, days: array<int, mixed>}>}
     */
    private function period(): array
    {
        // Fără capete alese încă la „Personalizat" → toată arhiva, ca la registrele-suror.
        $range = $this->timeMode() === self::TIME_MODE_CUSTOM ? $this->timeRange() : null;

        return CanteenWeek::period(
            $this->timeMode(),
            $this->timeRef()->toDateString(),
            ($range[0] ?? null)?->toDateString(),
            ($range[1] ?? null)?->toDateString(),
        );
    }

    // ── Linkurile de lucru ─────────────────────────────────────────────────────────────────────

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
            ->visible()
            ->whereDate('menu_date', Carbon::parse($date)->subWeek()->toDateString())
            ->first();
    }
}
