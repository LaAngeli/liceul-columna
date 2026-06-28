<?php

use App\Http\Controllers\CabinetCalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute — modul Calendar
|--------------------------------------------------------------------------
| Calendarul intern al cabinetului (elev/părinte). Scoping strict pe server prin garda din
| CalendarAccess (vezi controllerul). Fișier separat de web.php intenționat, ca să nu interfereze
| cu lucrul paralel pe site-ul public.
*/

Route::middleware('auth')->group(function (): void {
    Route::get('cabinet/calendar', [CabinetCalendarController::class, 'index'])->name('cabinet.calendar');
    Route::get('cabinet/calendar/events', [CabinetCalendarController::class, 'events'])->name('cabinet.calendar.events');
});
