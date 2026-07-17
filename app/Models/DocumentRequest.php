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
     * Id-ul notei contestate, purtat de cerere DIN DEPUNERE (payload) — contestațiile noi îl au
     * obligatoriu; cererile vechi (dinainte ca formularul să ceară nota) întorc null.
     */
    public function contestedGradeId(): ?int
    {
        $id = $this->payload['grade_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * SNAPSHOT-ul notei contestate, înghețat la depunere: disciplină, valoare/calificativ, data
     * acordării, profesorul. Cererea păstrează ce s-a contestat ATUNCI, chiar dacă nota se
     * schimbă între timp.
     *
     * @return array{subject: string, value: string|null, calificativ: string|null, graded_on: string|null, teacher: string|null}|null
     */
    public function contestedGradeSnapshot(): ?array
    {
        $snapshot = $this->payload['grade'] ?? null;

        if (! is_array($snapshot)) {
            return null;
        }

        return [
            'subject' => (string) ($snapshot['subject'] ?? ''),
            'value' => isset($snapshot['value']) && $snapshot['value'] !== '' ? (string) $snapshot['value'] : null,
            'calificativ' => isset($snapshot['calificativ']) && $snapshot['calificativ'] !== '' ? (string) $snapshot['calificativ'] : null,
            'graded_on' => isset($snapshot['graded_on']) ? (string) $snapshot['graded_on'] : null,
            'teacher' => isset($snapshot['teacher']) && $snapshot['teacher'] !== '' ? (string) $snapshot['teacher'] : null,
        ];
    }

    /** Eticheta notei contestate (din snapshot), cu numele brut al disciplinei — panou/PDF. */
    public function contestedGradeLabel(): ?string
    {
        $snapshot = $this->contestedGradeSnapshot();

        return $snapshot !== null ? self::composeGradeLabel($snapshot) : null;
    }

    /**
     * Compune eticheta „disciplină — valoare (dată) · profesor" dintr-un snapshot; separată ca
     * apelanții să poată substitui disciplina TRADUSĂ (cabinetul multilingv).
     *
     * @param  array{subject: string, value: string|null, calificativ: string|null, graded_on: string|null, teacher: string|null}  $snapshot
     */
    public static function composeGradeLabel(array $snapshot): string
    {
        $label = sprintf(
            '%s — %s (%s)',
            $snapshot['subject'],
            $snapshot['value'] ?? $snapshot['calificativ'] ?? '—',
            $snapshot['graded_on'] ?? '—',
        );

        return $snapshot['teacher'] !== null ? $label.' · '.$snapshot['teacher'] : $label;
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
