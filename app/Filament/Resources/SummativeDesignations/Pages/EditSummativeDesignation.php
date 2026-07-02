<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSummativeDesignation extends EditRecord
{
    protected static string $resource = SummativeDesignationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
