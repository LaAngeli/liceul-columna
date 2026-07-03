<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Fișa read-only a elevului. Ținta drill-down-ului „Corigenți" și a linkurilor inverse din
 * catalog (note/absențe/corecții) — accesibilă și diriginților, care nu pot edita fișa.
 */
class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * @return array<int, EditAction>
     */
    protected function getHeaderActions(): array
    {
        // Editarea rămâne doar pentru configuratori — EditAction se ascunde automat prin
        // StudentResource::canEdit() ({@see \App\Filament\Concerns\ManagedByConfigurators}).
        return [
            EditAction::make(),
        ];
    }
}
