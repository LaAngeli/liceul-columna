<?php

namespace App\Filament\Resources\CorigentaExams\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCorigentaExam extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = CorigentaExamResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
