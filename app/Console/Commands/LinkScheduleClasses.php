<?php

namespace App\Console\Commands;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use App\Models\SchoolClass;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Canonizare orar: leagă orarele „lecții" de clasa reală, după eticheta „Clasa {nume} {secțiune}"
 * (ex. „Clasa IX 2" → clasa cu name=IX, section=2). Nedistructiv + idempotent. Etichetele fără
 * potrivire rămân nelegate (raportate). Rulează: php artisan app:link-schedule-classes [--relink].
 */
class LinkScheduleClasses extends Command
{
    protected $signature = 'app:link-schedule-classes {--relink : Reevaluează și orarele deja legate}';

    protected $description = 'Leagă orarele „lecții" de clasa reală după etichetă (canonizare orar)';

    public function handle(): int
    {
        $query = Schedule::query()->where('type', ScheduleType::Lessons->value);

        if (! $this->option('relink')) {
            $query->whereNull('school_class_id');
        }

        $linked = 0;
        $skipped = 0;

        foreach ($query->get() as $schedule) {
            $class = $this->matchClass($schedule->label);

            if ($class === null) {
                $this->warn("Fără potrivire: {$schedule->label}");
                $skipped++;

                continue;
            }

            $schedule->update(['school_class_id' => $class->id]);
            $linked++;
        }

        $this->info("Legate: {$linked}. Nepotrivite: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * „Clasa {nume} {secțiune}" → clasa cu (name, section). Ultimul cuvânt e secțiunea, restul e numele.
     */
    private function matchClass(string $label): ?SchoolClass
    {
        $rest = trim(Str::after($label, 'Clasa '));

        // Str::after întoarce întreg șirul dacă „Clasa " lipsește.
        if ($rest === '' || $rest === trim($label)) {
            return null;
        }

        $pos = mb_strrpos($rest, ' ');

        if ($pos === false) {
            return null;
        }

        $name = trim(mb_substr($rest, 0, $pos));
        $section = trim(mb_substr($rest, $pos + 1));

        return SchoolClass::query()
            ->where('name', $name)
            ->where('section', $section)
            ->first();
    }
}
