<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use App\Models\ExamCommission;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExamCommission extends EditRecord
{
    protected static string $resource = ExamCommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Aceeași gardă ca la creare: după sincronizarea relației, președintele nu rămâne și membru.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof ExamCommission) {
            ExamCommissionResource::enforceDistinctPresident($record);
        }
    }
}
