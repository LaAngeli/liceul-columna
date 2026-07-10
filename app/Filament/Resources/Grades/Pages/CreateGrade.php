<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Grades\GradeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGrade extends CreateRecord
{
    use DisablesCreateAnother;
    use EnforcesGradeScope;

    protected static string $resource = GradeResource::class;

    // După creare, revenire la listă (pagina de editare e inaccesibilă profesorului) — același
    // comportament ca la absențe, și mai rapid pentru introducerea notelor în serie.
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
        return $this->enforceGradeScope($data);
    }
}
