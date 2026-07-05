<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Resources\Absences\AbsenceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAbsence extends EditRecord
{
    use EnforcesAbsenceScope;

    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    // După salvare, revenire la listă (nu rămâne pe formular).
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Trecem id-ul curent ca să se excludă din verificarea anti-duplicat.
        return $this->enforceAbsenceScope($data, (int) $this->getRecord()->getKey());
    }
}
