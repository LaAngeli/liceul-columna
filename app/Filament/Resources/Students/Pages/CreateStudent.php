<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = StudentResource::class;
}
