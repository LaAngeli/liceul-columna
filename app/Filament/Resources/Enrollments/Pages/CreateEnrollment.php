<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEnrollment extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = EnrollmentResource::class;
}
