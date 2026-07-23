<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
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

        return AnnouncementResource::normalizeAudience($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Announcement) {
            AnnouncementResource::syncAudience(
                $record,
                is_array($this->data['school_classes'] ?? null) ? $this->data['school_classes'] : [],
                is_array($this->data['students'] ?? null) ? $this->data['students'] : [],
                is_array($this->data['users'] ?? null) ? $this->data['users'] : [],
            );
        }
    }

    /**
     * După salvare → FIȘA anunțului, nu lista: fluxul e compune → recitește → publică,
     * iar butonul „Publică" stă pe fișă.
     */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
