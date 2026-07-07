<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\Concerns\HandlesDocumentFileMetadata;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    use HandlesDocumentFileMetadata;

    protected static string $resource = DocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withDocumentFileMetadata($data);
    }
}
