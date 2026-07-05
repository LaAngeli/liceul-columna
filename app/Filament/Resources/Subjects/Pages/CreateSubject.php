<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Subjects\SubjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SubjectResource::class;
}
