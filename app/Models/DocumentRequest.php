<?php

namespace App\Models;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * O cerere tipică depusă de familie (spec §4.3), generată PDF și transmisă secretariatului.
 *
 * @property DocumentRequestType $type
 * @property RequestStatus $status
 * @property array<string, mixed> $payload
 * @property Carbon|null $reviewed_at
 */
class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'student_id',
        'requested_by_user_id',
        'payload',
        'pdf_path',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentRequestType::class,
            'status' => RequestStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Secretariatul marchează cererea ca procesată (aprobată).
     */
    public function markProcessed(int $reviewerId): void
    {
        $this->update([
            'status' => RequestStatus::Approved,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
