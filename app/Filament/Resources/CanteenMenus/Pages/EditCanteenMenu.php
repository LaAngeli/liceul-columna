<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCanteenMenu extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = CanteenMenuResource::class;

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
