<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHoliday extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = HolidayResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
