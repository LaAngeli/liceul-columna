<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamCommissions extends ListRecords
{
    protected static string $resource = ExamCommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
