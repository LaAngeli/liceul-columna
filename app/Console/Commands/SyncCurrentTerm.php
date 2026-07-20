<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Setează `is_current` pe semestrul care conține data curentă (după intervalele starts_on/ends_on),
 * ca flag-ul să nu rămână stale. Rulat zilnic din scheduler (vezi routes/console.php). Idempotent.
 *
 * Alegerea semestrului curent:
 *   1. semestrul care CONȚINE azi (intervalul lui);
 *   2. în vacanță/gap → cel mai RECENT semestru început (starts_on <= azi);
 *   3. înainte de orice semestru → primul care urmează.
 * Astfel există MEREU exact un semestru curent (contează pentru fallback-ul derivării semestrului).
 */
class SyncCurrentTerm extends Command
{
    protected $signature = 'app:sync-current-term';

    protected $description = 'Setează is_current pe semestrul care conține data curentă (după intervalele de date)';

    public function handle(): int
    {
        $today = Carbon::today();

        $current = Term::forDate($today)
            ?? Term::query()
                ->whereNotNull('starts_on')
                ->whereDate('starts_on', '<=', $today)
                ->orderByDesc('starts_on')
                ->first()
            ?? Term::query()
                ->whereNotNull('starts_on')
                ->orderBy('starts_on')
                ->first();

        if (! $current instanceof Term) {
            $this->warn('Niciun semestru cu interval definit — nimic de setat.');

            return self::SUCCESS;
        }

        // Toate celelalte → false; curentul → true (doar dacă nu e deja).
        Term::query()->where('is_current', true)->whereKeyNot($current->getKey())->update(['is_current' => false]);

        if (! $current->is_current) {
            $current->update(['is_current' => true]);
        }

        // OGLINDA pe an: `academic_years.is_current` nu mai e un toggle manual, ci reflexia
        // semestrului curent (vezi App\Support\SchoolCalendar). Fără pasul ăsta, la rollover
        // scheduler-ul muta semestrul iar flagul de an rămânea pe anul încheiat — două adevăruri.
        $this->mirrorCurrentYear((int) $current->academic_year_id);

        $this->info(sprintf(
            'Semestrul curent: %s (%s – %s). Anul curent: %s.',
            $current->name,
            $current->starts_on?->toDateString() ?? '?',
            $current->ends_on?->toDateString() ?? '?',
            // `??` are semantică isset(): relația nelegată dă null fără `?->`.
            $current->academicYear->name ?? '?',
        ));

        return self::SUCCESS;
    }

    /** Marchează anul dat ca fiind curentul; stinge orice alt an rămas marcat. */
    private function mirrorCurrentYear(int $yearId): void
    {
        AcademicYear::query()
            ->where('is_current', true)
            ->whereKeyNot($yearId)
            ->update(['is_current' => false]);

        AcademicYear::query()
            ->whereKey($yearId)
            ->where('is_current', false)
            ->update(['is_current' => true]);
    }
}
