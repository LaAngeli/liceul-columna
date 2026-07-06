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

    // Atribuirile de responsabil de domeniu se schimbă rar → poll lent (5m). Evită default-ul tăcut
    // de 5s din trait-ul CanPoll, care ar fi re-rulat query-urile din canView+getStats la fiecare ciclu.
    protected ?string $pollingInterval = '5m';

    // Memoizare per-request: canView() și getStats() apelează amândouă pendingCount() — fără cache
    // s-ar executa același set de query-uri de 2 ori. Cache-ul static se resetează natural la finalul
    // cererii (instanța de widget e proaspătă per request).
    private static ?int $cachedPendingCount = null;

    // Cap de panou (bandă de categorie cockpit).
    protected function getHeading(): ?string
    {
        return __('panel.widgets.audiences_pending.title');
    }

    public static function canView(): bool
    {
        // Doar cine poate ATRIBUI un responsabil de domeniu (editează conturi = super/director/AO).
        // Prim-vicedirectorul vedea semnalul dar nu putea acționa — acțiunea instruită ducea la 403
        // (audit S-2/#34).
        $user = auth('web')->user();

        return $user instanceof User && $user->canManageAccounts() && self::pendingCount() > 0;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('panel.widgets.audiences_pending.title'), (string) self::pendingCount())
                ->description(__('panel.widgets.audiences_pending.description'))
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

    /**
     * Resetează cache-ul intra-request. În prod nu e necesar; testele care schimbă starea
     * (creează userii responsabili / mesaje) trebuie să-l cheme manual între aserții.
     */
    public static function flushCache(): void
    {
        self::$cachedPendingCount = null;
    }

    private static function pendingCount(): int
    {
        if (self::$cachedPendingCount !== null) {
            return self::$cachedPendingCount;
        }

        $unhandled = self::unhandledDomains();

        if ($unhandled === []) {
            return self::$cachedPendingCount = 0;
        }

        return self::$cachedPendingCount = Message::query()
            ->where('type', MessageType::Audience)
            ->whereIn('audience_domain', $unhandled)
            ->whereNull('read_at')
            ->count();
    }
}
