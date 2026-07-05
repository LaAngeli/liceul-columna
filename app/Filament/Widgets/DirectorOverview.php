<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Widgets\Concerns\CockpitStats;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Tablou de CONDUCERE + OPERAȚIONAL (director / prim-vicedirector / administrator operațional):
 * imaginea școlii și ce necesită decizie — NU volume brute din catalog (acelea sunt treabă
 * tehnică / a profesorului).
 */
class DirectorOverview extends StatsOverviewWidget
{
    use CockpitStats;

    // -3: sub triaj (-4). Redesign hybrid: metrica primară (Elevi) e în card-erou, alertele
    // (corigenți / de urmărit / fără diriginte) în „Necesită atenție" — aici rămân doar info-urile.
    protected static ?int $sort = -3;

    // Reîmprospătare la 60s: dashboard-ul „de conducere" e adesea lăsat deschis pe parcursul zilei.
    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth('web')->user()?->isManagement() ?? false;
    }

    protected function getStats(): array
    {
        // Doar informaționale (Elevi → card-erou; alertele → NeedsAttention). Activitatea personală
        // (grafic) rămâne în ActivityMonitor.
        return [
            Stat::make(__('panel.fields.classes'), SchoolClass::query()->count())
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->extraAttributes(self::cockpit())
                ->url(SchoolClassResource::getUrl('index')),
            Stat::make(__('panel.widgets.admin_overview.teachers'), Teacher::query()->count())
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->extraAttributes(self::cockpit()),
        ];
    }
}
