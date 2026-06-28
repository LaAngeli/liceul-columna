<?php

namespace App\Filament\Resources\CorigentaExams\Pages;

use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorigentaExams extends ListRecords
{
    protected static string $resource = CorigentaExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
