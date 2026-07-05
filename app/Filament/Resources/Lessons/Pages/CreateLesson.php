<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Lessons\LessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLesson extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = LessonResource::class;
}
