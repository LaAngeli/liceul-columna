<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeworkAssignments extends ListRecords
{
    protected static string $resource = HomeworkAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
