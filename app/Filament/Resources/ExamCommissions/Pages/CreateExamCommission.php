<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use App\Models\ExamCommission;
use Filament\Resources\Pages\CreateRecord;

class CreateExamCommission extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ExamCommissionResource::class;

    /**
     * Președintele nu rămâne și membru — garda rulează după ce Filament a sincronizat relația
     * (vezi {@see ExamCommissionResource::enforceDistinctPresident()}).
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof ExamCommission) {
            ExamCommissionResource::enforceDistinctPresident($record);
        }
    }
}
