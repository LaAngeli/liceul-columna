<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Subject;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SubjectResource::class;

    /**
     * Poziția în foaia matricolă se aplică DUPĂ creare, prin singura cale de scriere
     * ({@see Subject::placeInReportOrder}) — câmpul din formular nu se dehidratează,
     * deci inserarea pe o poziție ocupată împinge restul tranzacțional, fără duplicate.
     */
    protected function afterCreate(): void
    {
        $raw = $this->data['report_order'] ?? null;

        /** @var Subject $subject */
        $subject = $this->getRecord();

        Subject::placeInReportOrder($subject, is_numeric($raw) ? (int) $raw : null);
    }
}
