<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
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
}
