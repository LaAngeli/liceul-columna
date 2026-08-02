<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Models\Holiday;
use App\Support\SchoolCalendar;
use Filament\Resources\Pages\CreateRecord;

class CreateHoliday extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = HolidayResource::class;

    /**
     * După creare ne întoarcem în PLANIFICATOR, pe anul zilei tocmai adăugate.
     *
     * Implicit, Filament duce la pagina de EDITARE a înregistrării create — de acolo venea
     * senzația raportată că „formularul păstrează datele după creare": era, de fapt, fișa
     * salvată, nu un formular necurățat. Convenția panoului e oricum „după salvare → înapoi la
     * listă" (vezi celelalte pagini de creare), iar aici lista e chiar calendarul în care se vede
     * rezultatul: ziua nouă apare colorată, iar „Adaugă zi liberă" pornește un formular gol.
     */
    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        $year = $record instanceof Holiday
            ? SchoolCalendar::yearContaining($record->starts_on)
            : null;

        return static::getResource()::getUrl('index', array_filter(['an' => $year?->getKey()]));
    }
}
