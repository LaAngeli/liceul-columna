<?php

namespace App\Filament\Resources\CalendarEvents\Pages;

use App\Enums\CalendarEventScope;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Observers\CalendarEventObserver;
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

        // normalizeScope scoate `students` din payload; sincronizarea pivotului se face în afterCreate,
        // din form state (records există abia atunci).
        return CalendarEventResource::normalizeScope($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof CalendarEvent) {
            return;
        }

        CalendarEventResource::syncNominalAudience($record, [
            'students' => is_array($this->data['students'] ?? null) ? $this->data['students'] : [],
            'guardians' => is_array($this->data['guardians'] ?? null) ? $this->data['guardians'] : [],
            'families' => is_array($this->data['families'] ?? null) ? $this->data['families'] : [],
        ]);

        // Nominalul își notifică familiile ABIA acum — observerul `created` a sărit (pivot gol atunci).
        if ($record->visibility_scope === CalendarEventScope::Students) {
            app(CalendarEventObserver::class)->notifyNominalCreation($record);
        }
    }
}
