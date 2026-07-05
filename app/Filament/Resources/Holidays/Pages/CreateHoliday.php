<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHoliday extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = HolidayResource::class;
}
