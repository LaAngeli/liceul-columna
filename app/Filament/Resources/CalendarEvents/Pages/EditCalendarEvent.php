<?php

namespace App\Filament\Resources\CalendarEvents\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Models\CalendarEvent;
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
     * Pre-populează lista de elevi vizați (pivot) — nu e coloană, deci Filament nu o umple singur.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof CalendarEvent) {
            $data['students'] = $record->students()->pluck('students.id')->all();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CalendarEventResource::normalizeScope($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof CalendarEvent) {
            /** @var array<int, int|string> $students */
            $students = $this->data['students'] ?? [];
            CalendarEventResource::syncStudents($record, $students);
        }
    }
}
