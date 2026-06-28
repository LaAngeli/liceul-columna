<?php

use App\Console\Commands\ConsolidateAbsences;
use App\Console\Commands\SendHomeworkDigest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Digestul zilnic de teme (spec §5): un singur rezumat/zi/clasă, ca să nu spamăm familiile.
// Absențele/notele rămân instant (observers). Necesită ca scheduler-ul să ruleze (cron `schedule:run`).
Schedule::command(SendHomeworkDigest::class)->dailyAt('19:00');

// Consolidarea zilnică a absențelor nemotivate al căror termen de motivare (5 zile lucrătoare) a expirat (spec §2.1).
Schedule::command(ConsolidateAbsences::class)->dailyAt('06:00');
