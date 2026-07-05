<?php

namespace App\Filament\Resources\Terms\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Terms\TermResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTerm extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = TermResource::class;
}
