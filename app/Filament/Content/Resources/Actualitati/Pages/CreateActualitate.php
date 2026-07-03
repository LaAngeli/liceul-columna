<?php

namespace App\Filament\Content\Resources\Actualitati\Pages;

use App\Enums\PostType;
use App\Filament\Content\Resources\Actualitati\ActualitatiResource;
use App\Filament\Content\Support\BaseCreateArticle;

class CreateActualitate extends BaseCreateArticle
{
    protected static string $resource = ActualitatiResource::class;

    protected function postType(): PostType
    {
        return PostType::Actualitati;
    }
}
