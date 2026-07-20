<?php

namespace Database\Seeders;

use App\Actions\GenerateLegalHolidays;
use App\Actions\RecomputeMotivationDeadlines;
use App\Enums\HolidayType;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Date demo pentru secțiunea „Zile libere" (planificatorul anual): un set REALIST pe anul școlar
 * curent — sărbătorile legale ale R. Moldova (din {@see GenerateLegalHolidays}), vacanțele ca
 * INTERVALE și câteva zile instituționale — ca administratorul să vadă toate cele 4 categorii
 * colorate pe calendar și benzile lor pe axa Semestrelor. Toate marcate „[DEMO]" → curățabile de
 * `app:purge-demo-data` la go-live, când administratorul operațional le înlocuiește cu cele reale.
 *
 * Idempotent (șterge întâi „[DEMO]"/„Zi liberă (demo)" înainte de a recrea). Rulare:
 * `php artisan db:seed --class=DemoHolidaysSeeder`.
 */
class DemoHolidaysSeeder extends Seeder
{
    private const MARKER = '[DEMO]';

    public function run(): void
    {
        $year = SchoolCalendar::currentYear() ?? AcademicYear::query()->latest('id')->first();

        if ($year === null) {
            $this->command->warn('Niciun an școlar definit — sar peste zilele libere demo.');

            return;
        }

        [$from, $to] = SchoolCalendar::yearSpan($year);
        $startYear = $from->year;

        // Curățare idempotentă (ambele denumiri: marcajul standard + cel istoric).
        Holiday::query()
            ->where('name', 'like', self::MARKER.'%')
            ->orWhere('name', 'Zi liberă (demo)')
            ->get()
            ->each(fn (Holiday $holiday) => $holiday->delete());

        /** @var list<array{name: string, type: string, starts_on: string, ends_on: string|null}> $rows */
        $rows = [];

        // 1) Sărbătorile LEGALE care cad în anul școlar (datele reale din lege + Paștele calculat).
        foreach (app(GenerateLegalHolidays::class)->candidatesBetween($from, $to) as $candidate) {
            $rows[] = [
                'name' => self::MARKER.' '.$candidate['name'],
                'type' => HolidayType::LegalHoliday->value,
                'starts_on' => $candidate['starts_on'],
                'ends_on' => $candidate['ends_on'],
            ];
        }

        // 2) VACANȚELE ca intervale (toamna în anul de start, iarna peste pragul de an, primăvara
        //    în anul următor) — poziționate în interiorul anului școlar.
        $vacations = [
            ['Vacanța de toamnă', Carbon::create($startYear, 10, 27), Carbon::create($startYear, 11, 2)],
            ['Vacanța de iarnă', Carbon::create($startYear, 12, 27), Carbon::create($startYear + 1, 1, 11)],
            ['Vacanța de primăvară', Carbon::create($startYear + 1, 4, 25), Carbon::create($startYear + 1, 5, 3)],
        ];

        foreach ($vacations as [$name, $start, $end]) {
            if ($start->lt($from) || $start->gt($to)) {
                continue;
            }

            $rows[] = [
                'name' => self::MARKER.' '.$name,
                'type' => HolidayType::Vacation->value,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
            ];
        }

        // 3) Zile INSTITUȚIONALE (o zi metodică toamna, o zi liberă acordată de administrație).
        $institutional = [
            ['Zi metodică', Carbon::create($startYear, 11, 21)],
            ['Zi liberă acordată de administrație', Carbon::create($startYear + 1, 3, 2)],
        ];

        foreach ($institutional as [$name, $day]) {
            if ($day->lt($from) || $day->gt($to)) {
                continue;
            }

            $rows[] = [
                'name' => self::MARKER.' '.$name,
                'type' => HolidayType::InstitutionalDay->value,
                'starts_on' => $day->toDateString(),
                'ends_on' => null,
            ];
        }

        // Inserare în masă FĂRĂ evenimente (fiecare Holidays::flush + recalcul de termene per rând
        // ar fi de zeci de ori pe un seed) — apoi invalidăm cache-ul și recalculăm O SINGURĂ DATĂ.
        Holiday::withoutEvents(function () use ($rows): void {
            foreach ($rows as $row) {
                Holiday::create($row);
            }
        });

        Holidays::flush();
        app(RecomputeMotivationDeadlines::class)->run();

        $this->command->info('Zile libere demo: '.count($rows)." înregistrări pe anul „{$year->name}” (legale + vacanțe + instituționale).");
    }
}
