<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCanteenMenus extends ListRecords
{
    protected static string $resource = CanteenMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
