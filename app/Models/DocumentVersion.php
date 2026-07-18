<?php

namespace App\Models;

use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O versiune ARHIVATĂ a unui document static din bibliotecă (Documente utile, Faza 4): snapshot-ul
 * fișierului dinaintea unei înlocuiri — calea (fișierul rămâne pe disc), metadatele și cine îl
 * urcase, cu eticheta de versiune de la momentul arhivării. `created_at` = momentul înlocuirii.
 * Igiena fișierelor la ștergerea permanentă a documentului-mamă: {@see Document::booted}.
 *
 * @property int $id
 * @property int $document_id
 * @property string $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property string|null $version_label
 * @property int|null $uploaded_by_user_id
 */
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'version_label',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** Dimensiunea fișierului formatată uman (KB/MB), sau null dacă nu e cunoscută. */
    public function formattedSize(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        if ($this->file_size < 1024) {
            return $this->file_size.' B';
        }

        if ($this->file_size < 1024 * 1024) {
            return round($this->file_size / 1024, 1).' KB';
        }

        return round($this->file_size / (1024 * 1024), 1).' MB';
    }
}
