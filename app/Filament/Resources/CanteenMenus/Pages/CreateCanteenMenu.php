<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCanteenMenu extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CanteenMenuResource::class;

    /** După salvare → lista (convenția panoului), nu editarea zilei abia create. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
