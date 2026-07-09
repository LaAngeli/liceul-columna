<?php

namespace App\Actions;

use App\Models\Message;
use Illuminate\Http\UploadedFile;

/**
 * Stochează fișierele atașate unui mesaj pe discul PRIVAT (`local` = storage/app/private) și
 * înregistrează metadatele. Validarea (tip/mărime/număr) se face în controller ÎNAINTE de apel.
 */
class StoreMessageAttachments
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Message $message, array $files): void
    {
        foreach ($files as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store("message-attachments/{$message->id}", 'local');

            // Fișierul a trecut deja validarea (tip + mărime) în controller, deci mime-ul e cunoscut.
            $message->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
