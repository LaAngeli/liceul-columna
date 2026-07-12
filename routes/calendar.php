<?php

use App\Http\Controllers\CabinetCalendarController;
use App\Http\Middleware\EnsureFamilyCabinet;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute — modul Calendar
|--------------------------------------------------------------------------
| Calendarul intern al cabinetului (elev/părinte). Scoping strict pe server prin garda din
| CalendarAccess (vezi controllerul). Fișier separat de web.php intenționat, ca să nu interfereze
| cu lucrul paralel pe site-ul public.
|
| SetUserLocale: titlurile auto ale evenimentelor (vacanțe, termene, teme) sunt randate pe SERVER
| prin trans('cabinet_calendar.*') — fără el, o familie RU/EN vedea titlurile în RO (#37).
| EnsureFamilyCabinet pe pagina de index: gating UNIFORM cu restul cabinetului (personalul → /admin);
| ruta de events (XHR) rămâne gardată de scoping-ul din controller (abort_if fără elevi vizibili).
*/

Route::middleware(['auth', 'verified', SetUserLocale::class])->group(function (): void {
    Route::get('cabinet/calendar', [CabinetCalendarController::class, 'index'])
        ->middleware(EnsureFamilyCabinet::class)
        ->name('cabinet.calendar');
    Route::get('cabinet/calendar/events', [CabinetCalendarController::class, 'events'])->name('cabinet.calendar.events');
});
