<?php

namespace App\Models;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Observers\DocumentRequestObserver;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
#[ObservedBy(DocumentRequestObserver::class)]
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
        'review_note',
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
     * Secretariatul marchează cererea ca procesată (aprobată), opțional cu o notă
     * (ex. „Transmisă spre reexaminare — corecția #X" la fluxul contestație→corecție).
     */
    public function markProcessed(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => RequestStatus::Approved,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /**
     * Secretariatul RESPINGE cererea, cu motiv opțional. Familia e notificată (observer → StatusChange).
     */
    public function markRejected(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => RequestStatus::Rejected,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /**
     * withTrashed: cererea elevului ARHIVAT rămâne afișabilă (nume în tabel) și închizibilă de
     * administrație — fără el, relația era null și descărcarea PDF crăpa cu TypeError (500).
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
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
