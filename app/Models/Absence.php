<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Observers\AbsenceObserver;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon $occurred_on
 * @property bool $is_motivated
 * @property Carbon|null $motivation_deadline
 * @property Carbon|null $motivation_locked_at
 */
#[ObservedBy(AbsenceObserver::class)]
class Absence extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<AbsenceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_class_id',
        'term_id',
        'teacher_id',
        'occurred_on',
        'is_motivated',
        'motivation_deadline',
        'motivation_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'is_motivated' => 'boolean',
            'motivation_deadline' => 'date',
            'motivation_locked_at' => 'datetime',
        ];
    }

    /**
     * Elevul are o motivare APROBATĂ care acoperă ziua dată? Sursă pentru recalcularea lui
     * `is_motivated` când data absenței se mută (dovada acoperă o perioadă, nu o absență anume).
     */
    public function hasApprovedMotivationOn(Carbon|string $date): bool
    {
        return AbsenceMotivation::query()
            ->where('student_id', $this->student_id)
            ->where('status', RequestStatus::Approved)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
