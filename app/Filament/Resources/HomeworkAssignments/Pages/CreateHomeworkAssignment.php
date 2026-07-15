<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeworkAssignment extends CreateRecord
{
    use DisablesCreateAnother;
    use EnforcesHomeworkScope;

    protected static string $resource = HomeworkAssignmentResource::class;

    // După creare, revenire la listă — consecvent cu Note și Absențe.
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->enforceHomeworkScope($data, creating: true);
    }
}
