<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorigentaSession extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CorigentaSessionResource::class;
}
