<?php

namespace App\Filament\Resources\CalendarEvents\Pages;

use App\Enums\AudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\UserRole;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\FamilyTokens;
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
     * Pre-populează selecția audienței nominale (pivoturi, nu coloane) în câmpul MODULUI de reach:
     * elevii la „doar elevul", părinții MEMORAȚI la „doar părinții" (cu derivare din elevi pentru
     * evenimentele dinaintea memorării selecției), token-uri de familie la „ambii".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof CalendarEvent) {
            $nominal = $record->visibility_scope === CalendarEventScope::Students;
            $reach = $record->audience_reach;
            $pivotStudents = $record->students()->pluck('students.id')->all();

            $data['students'] = $nominal && $reach === AudienceReach::Student ? $pivotStudents : [];

            if ($nominal && $reach === AudienceReach::Guardians) {
                $picked = $record->users()->pluck('users.id')->all();

                // Evenimentele dinaintea memorării selecției n-au conturile alese — se derivă
                // părinții elevilor vizați (exact conturile care VĂD evenimentul la acest reach).
                $data['guardians'] = $picked !== [] ? $picked : User::query()
                    ->whereHas('students', fn ($query) => $query->whereKey($pivotStudents))
                    ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
                    ->pluck('id')
                    ->all();
            } else {
                $data['guardians'] = [];
            }

            $data['families'] = $nominal && $reach !== AudienceReach::Student && $reach !== AudienceReach::Guardians
                ? array_map(fn (int $id): string => FamilyTokens::student($id), $pivotStudents)
                : [];
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
            CalendarEventResource::syncNominalAudience($record, [
                'students' => is_array($this->data['students'] ?? null) ? $this->data['students'] : [],
                'guardians' => is_array($this->data['guardians'] ?? null) ? $this->data['guardians'] : [],
                'families' => is_array($this->data['families'] ?? null) ? $this->data['families'] : [],
            ]);
        }
    }
}
