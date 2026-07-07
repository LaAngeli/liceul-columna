<?php

namespace App\Filament\Resources\Documents\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * FileUpload salvează doar calea + numele fișierului; derivăm restul metadatelor (dimensiune, tip MIME)
 * din fișierul stocat, la creare și la editare. Coloanele rămân consecvente cu conținutul real chiar
 * dacă managerul înlocuiește sau șterge fișierul.
 */
trait HandlesDocumentFileMetadata
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withDocumentFileMetadata(array $data): array
    {
        $path = $data['file_path'] ?? null;

        if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
            $data['file_size'] = Storage::disk('local')->size($path);
            $data['mime_type'] = Storage::disk('local')->mimeType($path) ?: null;

            return $data;
        }

        $data['file_size'] = null;
        $data['mime_type'] = null;

        return $data;
    }
}
