<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSummativeDesignation extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SummativeDesignationResource::class;
}
