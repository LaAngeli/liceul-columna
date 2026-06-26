<?php

namespace App\Filament\Resources\AdmissionRequests\Pages;

use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdmissionRequests extends ListRecords
{
    protected static string $resource = AdmissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
