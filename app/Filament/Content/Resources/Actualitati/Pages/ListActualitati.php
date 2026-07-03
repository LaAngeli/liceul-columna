<?php

namespace App\Filament\Content\Resources\Actualitati\Pages;

use App\Filament\Content\Resources\Actualitati\ActualitatiResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActualitati extends ListRecords
{
    protected static string $resource = ActualitatiResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
