<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = AnnouncementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_user_id'] = auth()->id();

        return $data;
    }
}
