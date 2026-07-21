<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Documents\Concerns\HandlesDocumentFileMetadata;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    use HandlesDocumentFileMetadata;
    use PlacesRecordActionsWithForm;

    protected static string $resource = DocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
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
        // versiunea VECHE (fișier + metadate) se arhivează în Document::booted (updated) — Faza 4.
        $record = $this->getRecord();

        if (($data['file_path'] ?? null) !== $record->getAttribute('file_path')) {
            $data['uploaded_by_user_id'] = auth('web')->id();
        }

        return $this->withDocumentFileMetadata($data);
    }
}
