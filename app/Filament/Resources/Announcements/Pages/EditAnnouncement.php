<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Support\FamilyTokens;
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
     * Pre-populează selecțiile pivot (clase/elevi/părinți/familii/conturi) — nu sunt coloane,
     * Filament nu le umple. Audiența nominală umple câmpul MODULUI ei de reach: elevii la „doar
     * elevul", conturile la „doar părinții", token-urile de familie (pe elevii vizați) la „ambii".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Announcement) {
            $data['school_classes'] = $record->schoolClasses()->pluck('school_classes.id')->all();

            $pivotStudents = $record->students()->pluck('students.id')->all();
            $accounts = $record->users()->pluck('users.id')->all();

            $nominal = $record->audience === AnnouncementAudience::Students;
            $reach = $record->audience_reach;
            $guardiansMode = $nominal && $reach === AudienceReach::Guardians;

            $data['students'] = $nominal && $reach === AudienceReach::Student ? $pivotStudents : [];
            $data['families'] = $nominal && ! $guardiansMode && $reach !== AudienceReach::Student
                ? array_map(fn (int $id): string => FamilyTokens::student($id), $pivotStudents)
                : [];
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
            AnnouncementResource::syncAudience($record, [
                'school_classes' => is_array($this->data['school_classes'] ?? null) ? $this->data['school_classes'] : [],
                'students' => is_array($this->data['students'] ?? null) ? $this->data['students'] : [],
                'users' => is_array($this->data['users'] ?? null) ? $this->data['users'] : [],
                'guardians' => is_array($this->data['guardians'] ?? null) ? $this->data['guardians'] : [],
                'families' => is_array($this->data['families'] ?? null) ? $this->data['families'] : [],
            ]);
        }
    }

    /** După editare → înapoi pe fișă, unde stă „Publică". */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
