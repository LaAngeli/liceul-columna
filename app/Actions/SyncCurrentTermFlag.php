<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;

/**
 * Sursa UNICĂ a regulii „care e semestrul curent" (spec §2.2; audit M-2): flag-ul `is_current`
 * urmează intervalele de date, nu se setează manual. Folosită de comanda programată zilnic
 * (`app:sync-current-term`) ȘI de acțiunea „Sincronizează" din secțiunea Semestre — aceeași
 * regulă pe ambele căi, ca UI-ul să nu poată diverge de scheduler.
 *
 * Alegerea:
 *   1. semestrul care CONȚINE azi (intervalul lui);
 *   2. în vacanță/gap → cel mai RECENT semestru început (starts_on <= azi);
 *   3. înainte de orice semestru → primul care urmează.
 * Astfel există MEREU exact un semestru curent (fallback-ul derivării semestrului din dată).
 */
class SyncCurrentTermFlag
{
    /** Semestrul care AR TREBUI să fie curent la data dată — fără nicio scriere. */
    public function determine(?Carbon $today = null): ?Term
    {
        $today ??= Carbon::today();

        return Term::forDate($today)
            ?? Term::query()
                ->whereNotNull('starts_on')
                ->whereDate('starts_on', '<=', $today)
                ->orderByDesc('starts_on')
                ->first()
            ?? Term::query()
                ->whereNotNull('starts_on')
                ->orderBy('starts_on')
                ->first();
    }

    /**
     * Aplică regula: stinge orice alt `is_current`, aprinde semestrul determinat și oglindește
     * anul lui pe `academic_years.is_current` (vezi {@see SchoolCalendar} — anul
     * curent se DERIVĂ din semestru; oglinda există doar pentru raportări SQL directe).
     */
    public function run(?Carbon $today = null): ?Term
    {
        $current = $this->determine($today);

        if (! $current instanceof Term) {
            return null;
        }

        Term::query()->where('is_current', true)->whereKeyNot($current->getKey())->update(['is_current' => false]);

        if (! $current->is_current) {
            $current->update(['is_current' => true]);
        }

        $this->mirrorCurrentYear((int) $current->academic_year_id);

        return $current;
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
