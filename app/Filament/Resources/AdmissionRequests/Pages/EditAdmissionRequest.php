<?php

namespace App\Filament\Resources\AdmissionRequests\Pages;

use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdmissionRequest extends EditRecord
{
    protected static string $resource = AdmissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
