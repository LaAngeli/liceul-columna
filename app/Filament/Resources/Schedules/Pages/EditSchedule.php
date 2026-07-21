<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Schedules\ScheduleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchedule extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = ScheduleResource::class;

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
