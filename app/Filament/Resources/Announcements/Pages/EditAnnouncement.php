<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
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

    /**
     * Pre-populează selecțiile pivot (clase/elevi/conturi) — nu sunt coloane, Filament nu le umple.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Announcement) {
            $data['school_classes'] = $record->schoolClasses()->pluck('school_classes.id')->all();
            $data['students'] = $record->students()->pluck('students.id')->all();

            // Pivotul de conturi are DOUĂ roluri: „Conturi anume" (users) sau părinții aleși
            // direct la „Elevi/Părinți — doar părinții" (guardians). Se umple câmpul potrivit.
            $accounts = $record->users()->pluck('users.id')->all();
            $guardiansMode = $record->audience === AnnouncementAudience::Students
                && $record->audience_reach === AudienceReach::Guardians;
            $data['guardians'] = $guardiansMode ? $accounts : [];
            $data['users'] = $guardiansMode ? [] : $accounts;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AnnouncementResource::normalizeAudience($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Announcement) {
            AnnouncementResource::syncAudience(
                $record,
                is_array($this->data['school_classes'] ?? null) ? $this->data['school_classes'] : [],
                is_array($this->data['students'] ?? null) ? $this->data['students'] : [],
                is_array($this->data['users'] ?? null) ? $this->data['users'] : [],
                is_array($this->data['guardians'] ?? null) ? $this->data['guardians'] : [],
            );
        }
    }

    /** După editare → înapoi pe fișă, unde stă „Publică". */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
