<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCorigentaSession extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = CorigentaSessionResource::class;

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
