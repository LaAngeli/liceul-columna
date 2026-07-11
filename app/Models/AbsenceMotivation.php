<?php

namespace App\Models;

use App\Enums\AudienceDomain;
use App\Enums\RequestStatus;
use App\Observers\AbsenceMotivationObserver;
use App\Support\WorkingDays;
use Database\Factories\AbsenceMotivationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cerere de motivare a absențelor: părintele cere, dirigintele validează (§2.1).
 *
 * @property RequestStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string|null $document_path
 * @property Carbon|null $reviewed_at
 * @property bool $is_exception
 * @property int $student_id
 * @property Carbon|null $created_at
 */
#[ObservedBy(AbsenceMotivationObserver::class)]
class AbsenceMotivation extends Model implements Auditable
{
    use AuditableTrait;

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
        'is_exception',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'is_exception' => 'boolean',
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
     * Termenul-limită de validare al dirigintelui (spec §2.1): depunere + 2 zile lucrătoare.
     */
    public function validationDeadline(): ?Carbon
    {
        return $this->created_at !== null ? WorkingDays::add($this->created_at, 2) : null;
    }

    /**
     * Cerere în așteptare al cărei termen de validare (2 zile lucrătoare) a fost depășit.
     * Ziua-limită e INCLUSIVĂ (endOfDay) — aceeași convenție ca termenul familiei
     * (`whereDate(motivation_deadline, '<', today)`): dirigintele nu e marcat „restant"
     * din prima oră a zilei-limită, ci abia din ziua următoare.
     */
    public function isOverdue(): bool
    {
        $deadline = $this->validationDeadline();

        return $this->isPending() && $deadline !== null && $deadline->endOfDay()->isPast();
    }

    /**
     * Cine poate valida/respinge cererea: administrația întotdeauna; pentru cererile NORMALE —
     * dirigintele clasei elevului; pentru EXCEPȚII (tardive) — vicedirectorul pe educație (atribut §4.2).
     */
    public function canBeReviewedBy(User $user): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        // Excepțiile (motivări tardive) → DOAR vicedirectorul pe educație; super-adminul rămâne
        // break-glass. Ceilalți administratori le VĂD, dar nu le aprobă (decizia ține de educație).
        if ($this->is_exception) {
            return $user->isSuperAdmin() || $user->handlesAudienceDomain(AudienceDomain::Educatie);
        }

        // Cererile normale: administrația academică sau dirigintele clasei CURENTE a elevului.
        if ($user->isAdministrator()) {
            return true;
        }

        $teacher = $user->teacher;

        if ($teacher === null || $teacher->homeroomSchoolClassIds() === []) {
            return false;
        }

        // DOAR înmatricularea cea mai recentă: fostul diriginte (clasa de anul trecut) nu mai
        // validează motivările fostului elev — dreptul urmează notificarea ({@see Student::homeroomUser},
        // care merge tot la dirigintele clasei curente).
        $currentEnrollment = $this->student?->enrollments()
            ->latest('academic_year_id')
            ->first();

        return $currentEnrollment !== null
            && in_array((int) $currentEnrollment->school_class_id, $teacher->homeroomSchoolClassIds(), true);
    }

    /**
     * Aprobă: marchează ca MOTIVATE absențele elevului din perioada cerută și consemnează
     * dirigintele/data.
     */
    public function approve(int $reviewerId, ?string $note = null): void
    {
        // Marchează MOTIVATE + DEBLOCHEAZĂ absențele consolidate (cazul aprobării unei excepții).
        Absence::query()
            ->where('student_id', $this->student_id)
            ->whereBetween('occurred_on', [$this->period_start, $this->period_end])
            ->where('is_motivated', false)
            ->update(['is_motivated' => true, 'motivation_locked_at' => null]);

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
