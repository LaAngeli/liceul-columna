<?php

namespace App\Filament\Resources\DocumentRequests\Pages;

use App\Filament\Resources\DocumentRequests\DocumentRequestActions;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * Fișa cererii tipice: documentul familiei + urma procesării, cu TOATE deciziile în antet
 * (PDF / deschide corecție / procesează cu comentariu / respinge). Cererea nu se editează —
 * procesarea trece exclusiv prin acțiuni.
 */
class ViewDocumentRequest extends ViewRecord
{
    protected static string $resource = DocumentRequestResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DocumentRequestActions::pdf(),
            DocumentRequestActions::attachment(),
            DocumentRequestActions::openCorrection(),
            DocumentRequestActions::process(),
            DocumentRequestActions::reject(),
        ];
    }
}
