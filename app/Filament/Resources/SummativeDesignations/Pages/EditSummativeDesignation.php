<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSummativeDesignation extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = SummativeDesignationResource::class;

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
