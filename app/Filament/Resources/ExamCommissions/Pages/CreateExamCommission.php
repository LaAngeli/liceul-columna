<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExamCommission extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ExamCommissionResource::class;
}
