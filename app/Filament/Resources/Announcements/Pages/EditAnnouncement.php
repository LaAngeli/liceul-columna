<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = AnnouncementResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** După editare → înapoi pe fișă, unde stă „Publică". */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
