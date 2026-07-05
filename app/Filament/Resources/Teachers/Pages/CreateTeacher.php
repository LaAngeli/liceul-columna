<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = TeacherResource::class;
}
