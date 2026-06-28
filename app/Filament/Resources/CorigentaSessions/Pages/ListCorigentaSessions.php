<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorigentaSessions extends ListRecords
{
    protected static string $resource = CorigentaSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
