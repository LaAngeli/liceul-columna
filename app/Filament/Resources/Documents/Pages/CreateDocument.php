<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Documents\Concerns\HandlesDocumentFileMetadata;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    use DisablesCreateAnother;
    use HandlesDocumentFileMetadata;

    protected static string $resource = DocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = auth('web')->id();

        return $this->withDocumentFileMetadata($data);
    }
}
