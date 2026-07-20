<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Terms\TermResource;
use App\Models\AcademicYear;
use App\Support\SchoolCalendar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Tablou ACȚIONABIL pentru configuratori: anii școlari existenți care nu au încă semestre definite.
 *
 * Rollover-ul e momentul cel mai fragil al sistemului: dacă anul nou n-are semestre cu intervale,
 * derivarea semestrului din data notei nu găsește nimic, iar în septembrie notele ar fi căzut tăcut
 * în semestrul anului ÎNCHEIAT (garda din EnforcesGradeScope le respinge acum, dar refuzul e o
 * plasă, nu o soluție — soluția e să existe semestrele la timp).
 *
 * Semnalul e pasiv azi: cardul anului din listă spune „fără semestre", dar numai dacă cineva intră
 * pe acea pagină. Widget-ul îl promovează pe dashboard cu 30 de zile înainte de sfârșitul anului
 * curent — fereastra în care pregătirea chiar contează — și rămâne activ cât timp lipsa persistă.
 */
class AcademicYearNeedsTerms extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $pollingInterval = '5m';

    /** Cu câte zile înainte de finalul anului curent devine semnalul relevant. */
    private const LEAD_DAYS = 30;

    private static bool $computed = false;

    /** @var list<AcademicYear> */
    private static array $cached = [];

    protected function getHeading(): ?string
    {
        return __('panel.widgets.year_needs_terms.heading');
    }

    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null && $user->canConfigureSchool() && self::yearsWithoutTerms() !== [];
    }

    protected function getStats(): array
    {
        return array_map(
            static fn (AcademicYear $year): Stat => Stat::make(
                (string) $year->name,
                __('panel.widgets.year_needs_terms.value'),
            )
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->url(TermResource::getUrl('create')),
            self::yearsWithoutTerms(),
        );
    }

    /** Resetează cache-ul intra-request (necesar în teste care schimbă starea în același proces). */
    public static function flushCache(): void
    {
        self::$computed = false;
        self::$cached = [];
    }

    /**
     * Anii fără niciun semestru, semnalați DOAR când pregătirea lor e deja relevantă: anul curent
     * (dacă e cumva gol) și anii viitori, odată ce ne apropiem de finalul celui în curs. Un an
     * viitor creat cu ani înainte n-are de ce să alarmeze zilnic.
     *
     * @return list<AcademicYear>
     */
    private static function yearsWithoutTerms(): array
    {
        if (self::$computed) {
            return self::$cached;
        }

        $currentYear = SchoolCalendar::currentYear();
        $today = Carbon::today();

        // Fereastra se deschide cu LEAD_DAYS înainte de finalul anului curent. Fără an curent
        // (școală neconfigurată încă), orice an gol e relevant imediat.
        $windowOpen = $currentYear?->ends_on === null
            || $today->gte($currentYear->ends_on->copy()->subDays(self::LEAD_DAYS));

        self::$cached = array_values(AcademicYear::query()
            ->whereDoesntHave('terms')
            ->when(! $windowOpen, fn ($query) => $query->whereKey($currentYear?->getKey()))
            ->orderBy('starts_on')
            ->get()
            ->all());

        self::$computed = true;

        return self::$cached;
    }
}
