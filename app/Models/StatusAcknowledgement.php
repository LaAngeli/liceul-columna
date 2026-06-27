<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Confirmarea electronică a părintelui că a luat cunoștință de statutul corigent/amânat al elevului
 * (spec pct. 108–109). E auditată (Legea 133 §7) — urma „cine/când" rămâne în jurnal.
 *
 * @property StudentStatus $status
 * @property Carbon $acknowledged_at
 */
class StatusAcknowledgement extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'student_id',
        'term_id',
        'acknowledged_by_user_id',
        'status',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
