<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleType;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Tablou ACȚIONABIL pentru administratorul operațional (§3.2: „publică orarul"): ce tipuri de orar
 * (din cele 9 secțiuni Calendar) nu au încă date publicate — obligația lui de inserare. Fiecare
 * card duce direct la formularul de adăugare. Se ascunde automat când toate tipurile au date.
 */
class SchedulesToComplete extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    // Tipurile de orar lipsă se completează rar → poll lent (5m). Fără asta, default-ul tăcut de 5s
    // din trait-ul CanPoll ar re-rula scanarea celor 9 enum-uri la fiecare ciclu.
    protected ?string $pollingInterval = '5m';

    // Memoizare per-request: canView() și getStats() consumă același rezultat — fără cache ar fi
    // calculat de 2 ori. Folosim un flag boolean (NU ??= pe array) pentru că [] e o valoare validă
    // de „toate tipurile sunt acoperite", iar ??= pe [] ar reexecuta calculul.
    private static bool $missingTypesComputed = false;

    /** @var list<ScheduleType> */
    private static array $cachedMissingTypes = [];

    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null && $user->canManageSchedules() && self::missingTypes() !== [];
    }

    protected function getStats(): array
    {
        $createUrl = ScheduleResource::getUrl('create');

        return array_map(
            static fn (ScheduleType $type): Stat => Stat::make($type->label(), __('panel.widgets.schedules_to_complete.value'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->url($createUrl),
            self::missingTypes(),
        );
    }

    /**
     * Resetează cache-ul intra-request. În prod nu e necesar (instanță proaspătă/cerere),
     * dar testele care modifică starea în mijlocul aceluiași proces îl trebuie chemat manual.
     */
    public static function flushCache(): void
    {
        self::$missingTypesComputed = false;
        self::$cachedMissingTypes = [];
    }

    /**
     * Tipurile de orar (din cele 9) care nu au niciun rând PUBLICAT pe site.
     *
     * @return list<ScheduleType>
     */
    private static function missingTypes(): array
    {
        if (self::$missingTypesComputed) {
            return self::$cachedMissingTypes;
        }

        // pluck aplică cast-ul → normalizăm la valorile string ale enum-ului.
        $present = Schedule::query()
            ->where('is_public', true)
            ->pluck('type')
            ->map(static fn ($type): string => $type instanceof ScheduleType ? $type->value : (string) $type)
            ->all();

        self::$cachedMissingTypes = array_values(array_filter(
            ScheduleType::cases(),
            static fn (ScheduleType $type): bool => ! in_array($type->value, $present, true),
        ));
        self::$missingTypesComputed = true;

        return self::$cachedMissingTypes;
    }
}
