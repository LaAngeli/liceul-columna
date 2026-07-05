<?php

namespace App\Filament\Resources\AcademicYears\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcademicYear extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = AcademicYearResource::class;
}
