<?php

namespace App\Filament\Resources\AdmissionRequests\Pages;

use App\Filament\Resources\AdmissionRequests\AdmissionRequestActions;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * Fișa cererii de înscriere: documentul familiei + urma procesării, cu TOATE deciziile în
 * antet (contactat / înmatriculează / refuză / redeschide). Datele trimise de familie nu se
 * editează de personal — procesarea trece exclusiv prin acțiuni cu urmă.
 */
class ViewAdmissionRequest extends ViewRecord
{
    protected static string $resource = AdmissionRequestResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AdmissionRequestActions::markContacted(),
            AdmissionRequestActions::scheduleVisit(),
            AdmissionRequestActions::enroll(),
            AdmissionRequestActions::refuse(),
            AdmissionRequestActions::reopen(),
        ];
    }
}
