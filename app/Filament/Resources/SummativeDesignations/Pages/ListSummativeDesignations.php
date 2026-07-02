<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSummativeDesignations extends ListRecords
{
    protected static string $resource = SummativeDesignationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
