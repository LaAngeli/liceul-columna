<?php

namespace App\Filament\Widgets;

use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Indicator pentru conducere (spec §4.2): audiențe sosite pe un domeniu care încă NU are un
 * responsabil atribuit (au căzut pe director, prin fallback). Semnalează „de atribuit responsabil
 * de domeniu". Apare DOAR dacă există astfel de audiențe necitite.
 */
class AudiencesPendingAssignment extends StatsOverviewWidget
{
    protected static ?int $sort = 90;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isManagement() && self::pendingCount() > 0;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Audiențe fără responsabil de domeniu', (string) self::pendingCount())
                ->description('Atribuie un responsabil din formularul de utilizator (Domenii de audiență).')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }

    /**
     * Domeniile care nu au niciun cont responsabil atribuit.
     *
     * @return list<string>
     */
    private static function unhandledDomains(): array
    {
        $unhandled = [];
        foreach (AudienceDomain::cases() as $domain) {
            if (! User::query()->whereJsonContains('audience_domains', $domain->value)->exists()) {
                $unhandled[] = $domain->value;
            }
        }

        return $unhandled;
    }

    private static function pendingCount(): int
    {
        $unhandled = self::unhandledDomains();

        if ($unhandled === []) {
            return 0;
        }

        return Message::query()
            ->where('type', MessageType::Audience)
            ->whereIn('audience_domain', $unhandled)
            ->whereNull('read_at')
            ->count();
    }
}
