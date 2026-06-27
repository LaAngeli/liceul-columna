<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Statutul OFICIAL validat al unui elev pe semestru (spec §2.5 / #33): Consiliul profesoral + ordin
 * director. Primează asupra statutului calculat automat. Auditat (L133 §7).
 *
 * @property StudentStatus $status
 * @property string|null $order_reference
 * @property Carbon $validated_at
 */
class SemesterValidation extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'student_id',
        'term_id',
        'validated_by_user_id',
        'status',
        'order_reference',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'validated_at' => 'datetime',
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
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
