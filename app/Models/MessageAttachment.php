<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * Un fișier/imagine atașat unui mesaj. Conținutul stă pe discul PRIVAT (`local`); aici doar
 * metadatele. Descărcarea trece prin rută autentificată, autorizată la participanții firului.
 *
 * @property int $message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 */
class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** Imaginile pot fi previzualizate inline (thumbnail); restul se afișează ca fișier. */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Mărimea într-o formă lizibilă (ex. „2,4 MB"). */
    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
