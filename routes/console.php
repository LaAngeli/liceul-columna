<?php

use App\Console\Commands\ArchiveNotifications;
use App\Console\Commands\ConsolidateAbsences;
use App\Console\Commands\SendHomeworkDigest;
use App\Console\Commands\SyncCurrentTerm;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ORA LOCALĂ, explicit: scheduler-ul evaluează implicit în `app.timezone` (UTC), deci orele de mai
// jos rulau cu 2–3 ore mai târziu decât scria — digestul „de seară" ajungea la 22:00, iar comutarea
// semestrului se făcea după miezul nopții pe alt calendar decât cel al școlii. Backup-urile primiseră
// deja fixarea (vezi mai jos); comenzile de domeniu școlar rămăseseră pe UTC.
$scoala = 'Europe/Chisinau';

// Digestul zilnic de teme (spec §5): un singur rezumat/zi/clasă, ca să nu spamăm familiile.
// Absențele/notele rămân instant (observers). Necesită ca scheduler-ul să ruleze (cron `schedule:run`).
Schedule::command(SendHomeworkDigest::class)->dailyAt('19:00')->timezone($scoala);

// Consolidarea zilnică a absențelor nemotivate al căror termen de motivare (5 zile lucrătoare) a expirat (spec §2.1).
Schedule::command(ConsolidateAbsences::class)->dailyAt('06:00')->timezone($scoala);

// Semestrul „curent" (is_current) urmărește automat data — după intervalele starts_on/ends_on —
// și oglindește anul curent pe `academic_years.is_current` (sursa unică: App\Support\SchoolCalendar).
Schedule::command(SyncCurrentTerm::class)->dailyAt('00:05')->timezone($scoala);

// Retenția notificărilor (2026-07-21): cele CITITE mai vechi de `notifications.archive_after_days`
// trec automat în arhivă (nu se șterg; necititele nu se ating). Utilizatorii nu pot șterge
// notificări — arhivarea e singura cale de ieșire din lista principală, și e a sistemului.
Schedule::command(ArchiveNotifications::class)->dailyAt('03:10')->timezone($scoala);

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
// `->timezone(...)` — scheduler-ul Laravel evaluează implicit orele în `app.timezone` (UTC aici),
// deci „01:30" rula de fapt la 04:30 ora Chișinău. Fixat explicit pe ora locală, start 02:00.
Schedule::command('backup:clean')->dailyAt('02:00')->timezone('Europe/Chisinau');
Schedule::command('backup:run --only-db')->dailyAt('02:30')->timezone('Europe/Chisinau');
Schedule::command('backup:run --only-files')->weeklyOn(0, '03:30')->timezone('Europe/Chisinau');
Schedule::command('backup:monitor')->dailyAt('04:00')->timezone('Europe/Chisinau');
