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

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canManageSchedules() && self::missingTypes() !== [];
    }

    protected function getStats(): array
    {
        $createUrl = ScheduleResource::getUrl('create');

        return array_map(
            static fn (ScheduleType $type): Stat => Stat::make($type->label(), 'De completat')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->url($createUrl),
            self::missingTypes(),
        );
    }

    /**
     * Tipurile de orar (din cele 9) care nu au niciun rând PUBLICAT pe site.
     *
     * @return list<ScheduleType>
     */
    private static function missingTypes(): array
    {
        // pluck aplică cast-ul → normalizăm la valorile string ale enum-ului.
        $present = Schedule::query()
            ->where('is_public', true)
            ->pluck('type')
            ->map(static fn ($type): string => $type instanceof ScheduleType ? $type->value : (string) $type)
            ->all();

        return array_values(array_filter(
            ScheduleType::cases(),
            static fn (ScheduleType $type): bool => ! in_array($type->value, $present, true),
        ));
    }
}
