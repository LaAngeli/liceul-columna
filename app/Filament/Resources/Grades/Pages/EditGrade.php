<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Grades\GradeResource;
use Filament\Resources\Pages\EditRecord;

class EditGrade extends EditRecord
{
    use EnforcesGradeScope;

    protected static string $resource = GradeResource::class;

    // Notele nu se șterg (§1) — se anulează cu motiv din listă. Fără DeleteAction.
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->enforceGradeScope($data);
    }
}
