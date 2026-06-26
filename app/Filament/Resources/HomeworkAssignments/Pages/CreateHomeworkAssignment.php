<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\PreparesHomeworkData;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeworkAssignment extends CreateRecord
{
    use PreparesHomeworkData;

    protected static string $resource = HomeworkAssignmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareHomeworkData($data);
    }
}
