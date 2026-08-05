<?php

namespace App\Models;

use App\Enums\Calificativ;
use App\Enums\CorrectionStatus;
use App\Observers\GradeCorrectionObserver;
use Database\Factories\GradeCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Solicitare de corecție a unei note: profesorul cere, administrația aprobă (§3.1).
 *
 * AUDITABLE: cererea e actul care SCHIMBĂ valoarea unei note (PII academic al unui minor) —
 * orice atingere a rândului (motiv editat post-factum, verdict rescris) lasă urmă în jurnal
 * (§7 / L133), în simetrie cu corecțiile de teme.
 *
 * @property CorrectionStatus $status
 * @property numeric-string|null $old_value
 * @property numeric-string|null $new_value
 * @property Carbon|null $reviewed_at
 */
#[ObservedBy(GradeCorrectionObserver::class)]
class GradeCorrection extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<GradeCorrectionFactory> */
    use HasFactory;

    protected $fillable = [
        'grade_id',
        'requested_by_user_id',
        'document_request_id',
        'old_value',
        'new_value',
        'old_calificativ',
        'new_calificativ',
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
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * PROPUNEREA respectă aceeași scală ca nota pe care vrea s-o înlocuiască: întreg 1–10 la
     * numerice, simbol din {@see Calificativ} la celelalte.
     *
     * DE CE pe model, nu doar pe formular: cererea se depune din PATRU locuri (harta notelor,
     * tabelul de note, panoul zilei din catalog, procesarea unei contestații), iar regula pusă în
     * fiecare formular ține doar cât timp câmpul e VIZIBIL — vizibilitatea depinde de rezolvarea
     * notei-țintă, deci un câmp care nu se rezolvă rămâne și nevalidat. Măsurat: prin acea cale,
     * până și `maxValue(10)` nu se aplica, iar o propunere de 99 trecea.
     *
     * Aici invariantul nu mai depinde de UI: orice cale — formular, comandă, viitor API — trece
     * pe aici. Garda oglindește exact perechea de gărzi de pe {@see Grade}, ca o propunere să nu
     * poată promite ceva ce nota n-ar accepta la aprobare.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $correction): void {
            $value = $correction->getAttribute('new_value');

            if ($correction->isDirty('new_value') && $value !== null
                && ((float) $value !== floor((float) $value) || (float) $value < 1.0 || (float) $value > 10.0)) {
                throw ValidationException::withMessages([
                    'new_value' => __('panel.validation.grade.value_must_be_integer'),
                ]);
            }

            $calificativ = $correction->getAttribute('new_calificativ');

            if ($correction->isDirty('new_calificativ') && $calificativ !== null && $calificativ !== ''
                && Calificativ::tryFrom((string) $calificativ) === null) {
                throw ValidationException::withMessages([
                    'new_calificativ' => __('panel.validation.grade.calificativ_unknown', [
                        'list' => implode(', ', Calificativ::values()),
                    ]),
                ]);
            }
        });
    }

    public function isPending(): bool
    {
        return $this->status === CorrectionStatus::Pending;
    }

    /**
     * Aprobă: aplică noua valoare pe notă (declanșează recalcul medie prin observer) și
     * consemnează cine/când. Fără modificare silențioasă — totul e arhivat aici.
     */
    public function approve(int $reviewerId, ?string $note = null): void
    {
        $this->grade->update([
            'value' => $this->new_value,
            'calificativ' => $this->new_calificativ,
        ]);

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
     * Cererea rămâne fără obiect (nota a fost anulată între timp). Nu e o respingere — administrația
     * nu s-a pronunțat asupra ei — deci nu consemnăm un recenzent, doar motivul de sistem.
     */
    public function expire(): void
    {
        $this->update([
            'status' => CorrectionStatus::Expired,
            'reviewed_at' => now(),
            'review_note' => __('panel.actions.request_correction.expired_note'),
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

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
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

    /**
     * Contestația familiei din care a pornit corecția (fluxul contestație→corecție, #36) —
     * null pentru corecțiile cerute direct de profesor.
     *
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }
}
