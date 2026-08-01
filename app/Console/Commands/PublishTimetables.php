<?php

namespace App\Console\Commands;

use App\Actions\PublishClassTimetable;
use App\Models\SchoolClass;
use App\Observers\LessonObserver;
use Illuminate\Console\Command;

/**
 * Recompune orarele PUBLICATE din orarul structurat. În regim normal nu e nevoie de ea — orice
 * modificare de slot republică singură clasa ({@see LessonObserver}); comanda e
 * pentru scrierile care ocolesc observerii (import, seed) și pentru republicarea în masă după o
 * schimbare care afectează toate clasele (de pildă alte intervale în orarul sunetelor).
 */
class PublishTimetables extends Command
{
    protected $signature = 'app:publish-timetables
        {--class= : Doar clasele al căror nume începe cu acest prefix}';

    protected $description = 'Regenerează orarele publicate ale claselor din orarul structurat';

    public function handle(PublishClassTimetable $publish): int
    {
        $prefix = (string) ($this->option('class') ?? '');

        $classes = SchoolClass::query()
            ->when($prefix !== '', fn ($query) => $query->where('name', 'like', $prefix.'%'))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $published = 0;

        foreach ($classes as $class) {
            if ($publish->handle($class) !== null) {
                $published++;
            }
        }

        $this->info(sprintf('%d orare publicate din %d clase.', $published, $classes->count()));

        return self::SUCCESS;
    }
}
