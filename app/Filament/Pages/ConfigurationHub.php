<?php

namespace App\Filament\Pages;

use App\Enums\ConfigurationCategory;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Terms\TermResource;
use App\Models\AcademicYear;
use App\Support\ScheduleCoverage;
use App\Support\SchoolCalendar;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * HUB-ul secțiunii „Configurare" — aterizarea care arată STAREA configurării, pe categorii logice.
 *
 * De ce o pagină și nu un navigator cu drill-down: cele 9 secțiuni sunt un set FIX și mic. Un nivel
 * intermediar ar fi ascuns 3 din 4 categorii la fiecare vizită, transformând hub-ul într-un al
 * doilea meniu peste cel care există deja. Aici toate categoriile sunt vizibile simultan, iar
 * intrările rămân și în sidebar: sidebar = acces direct pentru cine știe ce vrea, hub = tabloul de
 * stare pentru cine vrea să vadă ce mai e de configurat.
 *
 * PRINCIPIU: vizibilitatea nu se re-declară NICĂIERI. Fiecare card întreabă `Resource::canAccess()`
 * și `Resource::canCreate()`; o matrice de roluri copiată aici s-ar desincroniza tăcut de policies
 * la prima schimbare. Categoria fără nicio secțiune vizibilă dispare, iar hub-ul însuși dispare din
 * sidebar când nu rămâne nimic (administratorul tehnic nu-l vede deloc).
 */
class ConfigurationHub extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    // Prima poziție în grup: e aterizarea, nu o secțiune printre celelalte.
    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'configurare';

    protected string $view = 'filament.catalog.configuration-hub';

    /** @var array<string, mixed>|null memoizare pe INSTANȚĂ (nu cache static — ar sări între utilizatori) */
    private ?array $categoriesMemo = null;

    /** @var array<string, int>|null */
    private ?array $badgeMemo = null;

    public static function getNavigationGroup(): ?string
    {
        // EXACT eticheta tradusă din navigationGroups() — Filament grupează pe label, nu pe cheie.
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.config_hub.title');
    }

    public function getTitle(): string
    {
        return __('panel.config_hub.title');
    }

    public function getSubheading(): ?string
    {
        return __('panel.config_hub.subtitle');
    }

    /** Hub-ul apare doar dacă utilizatorul are măcar o secțiune de configurare vizibilă. */
    public static function canAccess(): bool
    {
        foreach (ConfigurationCategory::cases() as $category) {
            foreach ($category->resources() as $resource) {
                if ($resource::canAccess()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Categoriile cu secțiunile vizibile utilizatorului curent. Categoria goală nu se randează.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, sections: array<int, array{title: string, url: string, count: int|null, badge: array{label: string, color: string}|null}>}>
     */
    public function categories(): array
    {
        if ($this->categoriesMemo !== null) {
            return $this->categoriesMemo['data'];
        }

        $out = [];

        foreach (ConfigurationCategory::cases() as $category) {
            $sections = [];

            foreach ($category->resources() as $resource) {
                if (! $resource::canAccess()) {
                    continue;
                }

                $sections[] = [
                    // O singură denumire pentru fiecare secțiune: cea din sidebar.
                    'title' => (string) $resource::getNavigationLabel(),
                    'url' => $this->urlFor($resource),
                    'count' => $this->countFor($resource),
                    'badge' => $this->badgeFor($resource),
                ];
            }

            if ($sections === []) {
                continue;
            }

            $out[] = [
                'key' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
                'icon' => $category->icon(),
                'sections' => $sections,
            ];
        }

        $this->categoriesMemo = ['data' => $out];

        return $out;
    }

    /** Anul școlar în care se configurează acum — reperul de sus al paginii. */
    public function currentYearLabel(): ?string
    {
        return SchoolCalendar::currentYear()?->name;
    }

    /**
     * Linkul secțiunii. Doar `Resource::getUrl('index')`, eventual cu parametrii pe care
     * navigatoarele îi VALIDEAZĂ deja la citire (`?an=`) — filtrele de tabel din URL n-ar avea
     * efect, fiindcă niciun filtru din panou nu-și citește valoarea din request.
     *
     * @param  class-string  $resource
     */
    private function urlFor(string $resource): string
    {
        // Paginile au o singură rută, fără parametri de navigator.
        if (! is_subclass_of($resource, Resource::class)) {
            return $resource::getUrl();
        }

        $yearId = SchoolCalendar::currentYearId();

        // Secțiunile cu pastile pe ani aterizează direct în anul curent.
        $yearScoped = in_array($resource, [TermResource::class], true);

        return ($yearScoped && $yearId !== null)
            ? $resource::getUrl('index', ['an' => $yearId])
            : $resource::getUrl('index');
    }

    /**
     * Numărul de înregistrări al secțiunii — semnal de „cât e configurat". Null acolo unde numărul
     * brut n-ar spune nimic util.
     *
     * @param  class-string  $resource
     */
    private function countFor(string $resource): ?int
    {
        // Paginile (ex. regulile de notare) nu sunt resurse — nu există „câte" de numărat.
        if (! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        // Prin `getEloquentQuery()`, NU pe model direct: resursele scoped (orarul structurat, de
        // exemplu) trebuie să arate perimetrul utilizatorului, altfel cardul promitea 507 lecții
        // unui profesor care, la click, vedea doar clasele lui.
        return (int) $resource::getEloquentQuery()->count();
    }

    /**
     * Badge-ul de stare, o singură dată, în ordinea de prioritate: mai întâi ce cere ACȚIUNE
     * („N fără date"), apoi limitarea de drept („Doar citire"). Semnalul de acțiune apare doar
     * unde absența e cu adevărat o patologie și unde definiția lui există deja în cod — un semnal
     * fals e mai rău decât niciunul.
     *
     * @param  class-string  $resource
     * @return array{label: string, color: string}|null
     */
    private function badgeFor(string $resource): ?array
    {
        // O pagină de consultare (regulile de notare) e read-only prin natura ei, nu prin drept.
        if (! is_subclass_of($resource, Resource::class)) {
            return [
                'label' => (string) __('panel.config_hub.reference'),
                'color' => 'gray',
            ];
        }

        // Cine nu poate scrie primește starea lui, nu o sarcină: „N de configurat" e o CHEMARE LA
        // ACȚIUNE, iar afișarea ei unui rol fără drept de scriere e o cerință pe care n-o poate
        // executa (prins la verificarea live — profesorul vedea „1 de configurat" pe Orare).
        if (! $resource::canCreate()) {
            return [
                'label' => (string) __('panel.config_hub.read_only'),
                'color' => 'gray',
            ];
        }

        $missing = $this->missingFor($resource);

        if ($missing > 0) {
            return [
                // `trans_choice`, nu `__`: șirul e pluralizat, iar `__` l-ar afișa BRUT
                // („[1]1 de configurat|[2,*]…") — vizibil în UI, prins tot la verificarea live.
                'label' => trans_choice('panel.config_hub.needs_setup', $missing, ['count' => $missing]),
                'color' => 'warning',
            ];
        }

        return null;
    }

    /**
     * Câte „goluri de configurare" are secțiunea.
     *
     * @param  class-string  $resource
     */
    private function missingFor(string $resource): int
    {
        $this->badgeMemo ??= [
            // Tipuri de orar fără niciun tabel PUBLICAT (definiția unică — vezi ScheduleCoverage).
            ScheduleResource::class => count(ScheduleCoverage::missingTypes()),
            // Ani școlari fără niciun semestru definit: fundația pe care stă tot restul.
            AcademicYearResource::class => AcademicYear::query()->whereDoesntHave('terms')->count(),
        ];

        return $this->badgeMemo[$resource] ?? 0;
    }
}
