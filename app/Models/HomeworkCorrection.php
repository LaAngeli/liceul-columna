<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use App\Observers\HomeworkCorrectionObserver;
use Database\Factories\HomeworkCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Solicitare de corecție a unei TEME: profesorul-autor cere, Directorul / Prim-vicedirectorul /
 * Administratorul Operațional aprobă (decizia beneficiarului, 2026-07-15 — spre deosebire de
 * corecțiile de notă, unde AO doar vede arhiva). Câmpurile corectabile sunt cele de conținut
 * (subiect / sarcină obligatorie / sarcină opțională); `new_*` null = câmp nemodificat.
 *
 * AUDITABLE: cererea e actul care SCHIMBĂ conținut văzut de familii — orice atingere a rândului
 * (motiv editat post-factum, verdict rescris) trebuie să lase urmă în jurnal (§7 / L133).
 *
 * @property CorrectionStatus $status
 * @property Carbon|null $reviewed_at
 */
#[ObservedBy(HomeworkCorrectionObserver::class)]
class HomeworkCorrection extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<HomeworkCorrectionFactory> */
    use HasFactory;

    protected $fillable = [
        'homework_assignment_id',
        'requested_by_user_id',
        'old_topic',
        'new_topic',
        'old_required_task',
        'new_required_task',
        'old_optional_task',
        'new_optional_task',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => CorrectionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === CorrectionStatus::Pending;
    }

    /**
     * Aprobă: aplică pe temă DOAR câmpurile propuse (new_* non-null) și consemnează cine/când.
     * Fără modificare silențioasă — arhiva păstrează vechi → nou.
     */
    public function approve(int $reviewerId, ?string $note = null): void
    {
        $changes = array_filter([
            'topic' => $this->new_topic,
            'required_task' => $this->new_required_task,
            'optional_task' => $this->new_optional_task,
        ], fn (?string $value): bool => $value !== null);

        if ($changes !== [] && $this->homeworkAssignment !== null) {
            $this->homeworkAssignment->update($changes);
        }

        $this->update([
            'status' => CorrectionStatus::Approved,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    public function reject(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => CorrectionStatus::Rejected,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /**
     * Cererea rămâne fără obiect (tema a fost retrasă/ștearsă între timp). Nu e o respingere —
     * administrația nu s-a pronunțat — deci fără recenzent, doar motivul de sistem.
     */
    public function expire(): void
    {
        $this->update([
            'status' => CorrectionStatus::Expired,
            'reviewed_at' => now(),
            'review_note' => __('panel.actions.homework_correction.expired_note'),
        ]);
    }

    /** Solicitantul retrage cererea înainte să fie judecată. Rămâne în arhivă, nu se șterge. */
    public function withdraw(): void
    {
        $this->update([
            'status' => CorrectionStatus::Withdrawn,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Tema vizată — cu withTrashed: cererea e ISTORIC și rămâne citibilă și după retragerea temei.
     *
     * @return BelongsTo<HomeworkAssignment, $this>
     */
    public function homeworkAssignment(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class)->withTrashed();
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
