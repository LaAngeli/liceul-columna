<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use App\Observers\HomeworkAssignmentObserver;
use Database\Factories\HomeworkCorrectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * REGISTRUL corecțiilor de temă. Din 2026-07-31 (decizia beneficiarului) corecția e DIRECTĂ:
 * profesorul-autor și dirigintele clasei editează conținutul fără aprobare, iar fiecare schimbare
 * se consemnează automat aici (vechi → nou, cine, când — vezi {@see HomeworkAssignmentObserver}).
 * Fluxul vechi cerere → aprobare/respingere a fost eliminat; rândurile lui istorice rămân în
 * registru cu stările lor (aprobat/respins/retras/expirat) — arhiva nu se rescrie.
 *
 * Câmpurile consemnate sunt cele de conținut (subiect / sarcină obligatorie / sarcină opțională);
 * `new_*` null = câmp neatins. `requested_by` = cine a operat corecția; la rândurile directe
 * coincide cu `reviewed_by` (aplicată pe loc).
 *
 * AUDITABLE: registrul documentează conținut văzut de familii — orice atingere a rândului
 * trebuie să lase urmă în jurnal (§7 / L133).
 *
 * @property CorrectionStatus $status
 * @property Carbon|null $reviewed_at
 */
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

    /**
     * Rândul acesta e o corecție DIRECTĂ (regimul curent), nu o cerere din fluxul istoric:
     * operatorul și „recenzentul" sunt aceeași persoană, aplicată pe loc.
     */
    public function isDirect(): bool
    {
        return $this->status === CorrectionStatus::Approved
            && $this->reviewed_by_user_id !== null
            && $this->reviewed_by_user_id === $this->requested_by_user_id;
    }

    /**
     * Consemnează o corecție DIRECTĂ aplicată deja pe temă: snapshot vechi → nou doar pe
     * câmpurile atinse. Sursă unică pentru observer și seedere.
     *
     * @param  array<string, string|null>  $old  valorile de dinainte (topic/required_task/optional_task)
     * @param  array<string, string|null>  $new  valorile de după, DOAR cheile schimbate
     */
    public static function recordApplied(
        HomeworkAssignment $homework,
        array $old,
        array $new,
        ?int $byUserId,
        ?string $reason = null,
    ): self {
        return self::query()->create([
            'homework_assignment_id' => $homework->id,
            'requested_by_user_id' => $byUserId,
            'old_topic' => array_key_exists('topic', $new) ? ($old['topic'] ?? null) : null,
            'new_topic' => $new['topic'] ?? null,
            'old_required_task' => array_key_exists('required_task', $new) ? ($old['required_task'] ?? null) : null,
            'new_required_task' => $new['required_task'] ?? null,
            'old_optional_task' => array_key_exists('optional_task', $new) ? ($old['optional_task'] ?? null) : null,
            'new_optional_task' => $new['optional_task'] ?? null,
            'reason' => $reason,
            'status' => CorrectionStatus::Approved,
            'reviewed_by_user_id' => $byUserId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Tema vizată — cu withTrashed: corecția e ISTORIC și rămâne citibilă și după retragerea temei.
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
