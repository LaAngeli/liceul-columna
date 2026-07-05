<?php

namespace App\Filament\Resources\AdmissionRequests\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmissionRequest extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = AdmissionRequestResource::class;
}
