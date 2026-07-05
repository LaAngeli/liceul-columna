<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Resources\Absences\AbsenceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsence extends CreateRecord
{
    use DisablesCreateAnother;
    use EnforcesAbsenceScope;

    protected static string $resource = AbsenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->enforceAbsenceScope($data);
    }
}
