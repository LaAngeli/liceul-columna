<?php

namespace App\Filament\Resources\SchoolClasses\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolClass extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SchoolClassResource::class;
}
