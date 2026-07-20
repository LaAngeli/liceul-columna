<?php

use App\Support\SchoolCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Normalizează `admission_requests.scheduled_visit_at` la UTC (fix sistemic de fus orar,
 * 2026-07-21): câmpul se introduce prin singurul DateTimePicker al aplicației, care până acum
 * salva ora tastată VERBATIM (ora locală a Chișinăului etichetată drept UTC). Odată cu
 * FilamentTimezone global, pickerul convertește corect local→UTC la salvare — deci valorile
 * VECHI trebuie aduse la aceeași semantică, altfel s-ar afișa cu +2/+3 ore după fix.
 *
 * Conversia e per-valoare (DST-aware prin Carbon, nu offset fix). `messages.scheduled_at`
 * (audiențe) NU se normalizează: inputul lui e custom (non-Filament), stocat ȘI afișat verbatim
 * peste tot — rămâne „ceas-de-perete", consecvent cu el însuși.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('admission_requests')->whereNotNull('scheduled_visit_at')->get(['id', 'scheduled_visit_at']) as $row) {
            DB::table('admission_requests')->where('id', $row->id)->update([
                'scheduled_visit_at' => Carbon::parse((string) $row->scheduled_visit_at, SchoolCalendar::TIMEZONE)
                    ->utc()
                    ->toDateTimeString(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('admission_requests')->whereNotNull('scheduled_visit_at')->get(['id', 'scheduled_visit_at']) as $row) {
            DB::table('admission_requests')->where('id', $row->id)->update([
                'scheduled_visit_at' => Carbon::parse((string) $row->scheduled_visit_at, 'UTC')
                    ->timezone(SchoolCalendar::TIMEZONE)
                    ->toDateTimeString(),
            ]);
        }
    }
};
