<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Grades\GradeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGrade extends CreateRecord
{
    use EnforcesGradeScope;

    protected static string $resource = GradeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->enforceGradeScope($data);
    }
}
