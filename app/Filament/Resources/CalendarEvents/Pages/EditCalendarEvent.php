<?php

namespace App\Filament\Resources\CalendarEvents\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCalendarEvent extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = CalendarEventResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CalendarEventResource::normalizeScope($data);
    }
}
