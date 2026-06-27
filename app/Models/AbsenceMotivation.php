<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Observers\AbsenceMotivationObserver;
use Database\Factories\AbsenceMotivationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cerere de motivare a absențelor: părintele cere, dirigintele validează (§2.1).
 *
 * @property RequestStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $reviewed_at
 */
#[ObservedBy(AbsenceMotivationObserver::class)]
class AbsenceMotivation extends Model
{
    /** @use HasFactory<AbsenceMotivationFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'requested_by_user_id',
        'reason',
        'period_start',
        'period_end',
        'document_path',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    /**
     * Aprobă: marchează ca MOTIVATE absențele elevului din perioada cerută și consemnează
     * dirigintele/data.
     */
    public function approve(int $reviewerId, ?string $note = null): void
    {
        Absence::query()
            ->where('student_id', $this->student_id)
            ->whereBetween('occurred_on', [$this->period_start, $this->period_end])
            ->where('is_motivated', false)
            ->update(['is_motivated' => true]);

        $this->update([
            'status' => RequestStatus::Approved,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    public function reject(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => RequestStatus::Rejected,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
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
