<?php

namespace App\Filament\Resources\CorigentaExams\Pages;

use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCorigentaExam extends EditRecord
{
    protected static string $resource = CorigentaExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
