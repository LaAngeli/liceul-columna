<?php

namespace App\Actions;

use App\Models\Message;
use Illuminate\Http\UploadedFile;

/**
 * Stochează fișierele atașate unui mesaj pe discul PRIVAT (`local` = storage/app/private) și
 * înregistrează metadatele. Validarea (tip/mărime/număr) se face ÎNAINTE de apel, cu regulile
 * din {@see validationRules()} — aceleași în ambele poște.
 */
class StoreMessageAttachments
{
    /**
     * Regulile de validare a atașamentelor — SURSĂ UNICĂ, folosită de poșta cabinetului (Inertia)
     * ȘI de acțiunile Filament ale personalului. Limitele vin din `config/messaging.php`.
     *
     * Fără ele partajate, panoul staff ar fi putut atașa tipuri (svg/html) pe care cabinetul le
     * interzice fiindcă se pot executa la servire inline (XSS).
     *
     * @return array<string, array<int, string>>
     */
    public static function validationRules(string $key = 'files'): array
    {
        $extensions = implode(',', (array) config('messaging.attachments.extensions', []));

        return [
            $key => ['nullable', 'array', 'max:'.(int) config('messaging.attachments.max_files', 5)],
            $key.'.*' => ['file', 'max:'.(int) config('messaging.attachments.max_file_kb', 8192), 'mimes:'.$extensions],
        ];
    }

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
