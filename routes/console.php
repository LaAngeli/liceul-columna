<?php

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
