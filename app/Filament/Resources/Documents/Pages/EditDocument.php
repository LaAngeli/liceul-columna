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
        // La ÎNLOCUIREA fișierului, evidența „cine a urcat" trece pe cel care l-a înlocuit —
        // altfel coloana afișată rămânea pe primul uploader (răspunderea aparentă greșită);
        // fișierul VECHI se șterge de pe disk în Document::booted (updated).
        $record = $this->getRecord();

        if (($data['file_path'] ?? null) !== $record->getAttribute('file_path')) {
            $data['uploaded_by_user_id'] = auth('web')->id();
        }

        return $this->withDocumentFileMetadata($data);
    }
}
