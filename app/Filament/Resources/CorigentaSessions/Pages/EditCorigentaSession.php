<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCorigentaSession extends EditRecord
{
    protected static string $resource = CorigentaSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
