<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = StudentResource::class;

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
     * Schimbările cu IMPACT dincolo de fișă sunt spuse pe loc (cerința 2026-07-21): grupa la
     * engleză mută elevul la profesorul celeilalte grupe (alocări + catalog), limba 2 îl mută
     * între disciplinele de L2. Nu blocăm — mutările sunt legitime administrativ — dar
     * configuratorul pleacă știind ce module verifică. Modificarea în sine e deja jurnalizată
     * (Student e Auditable).
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        $impacts = [];

        if ($record->wasChanged('english_group')) {
            $impacts[] = (string) __('panel.forms.student.changed_group_impact');
        }

        if ($record->wasChanged('second_language')) {
            $impacts[] = (string) __('panel.forms.student.changed_language_impact');
        }

        if ($impacts !== []) {
            Notification::make()
                ->warning()
                ->persistent()
                ->title(__('panel.forms.student.changed_impact_title'))
                ->body(implode(' ', $impacts))
                ->send();
        }
    }
}
