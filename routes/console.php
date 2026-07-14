<?php

use App\Console\Commands\ConsolidateAbsences;
use App\Console\Commands\SendHomeworkDigest;
use App\Console\Commands\SyncCurrentTerm;
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

// Semestrul „curent" (is_current) urmărește automat data — după intervalele starts_on/ends_on.
Schedule::command(SyncCurrentTerm::class)->dailyAt('00:05');

/*
 * Reziliență (#41) — backup pe DOUĂ ritmuri, fiindcă datele au două viteze diferite:
 *
 *   BAZA DE DATE (~16 MB) se schimbă în fiecare zi — note, absențe, mesaje, cereri, ȘI textele
 *   site-ului (articolele/paginile stau în DB, nu în fișiere). → ZILNIC. E ieftină: un an întreg
 *   de arhive zilnice încape în ~6 GB.
 *
 *   FIȘIERELE (~380 MB: bibliotecă, galerii, imagini articole) se schimbă rar. Arhivarea lor
 *   zilnică era risipă — ele umflau backup-ul la 428 MB și consumau plafonul de retenție,
 *   scurtând istoricul bazei (partea care contează). → SĂPTĂMÂNAL (duminică noaptea).
 *
 * `backup:monitor` verifică zilnic sănătatea (vechime + mărime) și alertează pe email — dar DOAR
 * dacă BACKUP_NOTIFICATION_EMAIL și un SMTP real sunt setate în `.env`. Altfel, tăcere.
 *
 * Toate rulează doar cu scheduler activ (cron `schedule:run`) — relevant în producție.
 */
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('01:30');
Schedule::command('backup:run --only-files')->weeklyOn(0, '02:30');
Schedule::command('backup:monitor')->dailyAt('03:00');
