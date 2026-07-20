<?php

namespace App\Enums;

use App\Support\Holidays;

/**
 * Categoria unei zile libere — separă vizual și statistic ce ÎNSEAMNĂ ziua: sărbătoare legală
 * (Codul muncii art. 111 — vine din lege, nu din decizia școlii), vacanță școlară (interval din
 * structura anului aprobată de MEC), zi instituțională (decizia liceului: hram, zi metodică) sau
 * altă suspendare (situații excepționale: cod meteo, doliu). Toate opresc lecțiile la fel —
 * {@see Holidays} nu distinge — dar administrarea și afișarea lor diferă.
 */
enum HolidayType: string
{
    case LegalHoliday = 'legal';
    case Vacation = 'vacation';
    case InstitutionalDay = 'institutional';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.holiday_type.'.$this->value);
    }

    /** Culoarea Filament (badge-uri în formulare/notificări). */
    public function color(): string
    {
        return match ($this) {
            self::LegalHoliday => 'info',
            self::Vacation => 'success',
            self::InstitutionalDay => 'warning',
            self::Other => 'gray',
        };
    }

    // Prezentarea din planificator (culorile celulelor/punctelor) NU stă aici: Tailwind compilează
    // doar clasele văzute în globurile @source ale temei (app/Filament/**, resources/views/**), iar
    // enum-urile sunt în afara lor — clasele ar rămâne necompilate. Vezi ListHolidays::cellClassesFor().
}
