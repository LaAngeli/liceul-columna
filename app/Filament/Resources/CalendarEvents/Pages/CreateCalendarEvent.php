<?php

namespace App\Filament\Resources\CalendarEvents\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCalendarEvent extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CalendarEventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return CalendarEventResource::normalizeScope($data);
    }
}
