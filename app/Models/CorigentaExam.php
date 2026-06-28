<?php

namespace App\Models;

use App\Enums\CorigentaSeason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Intrare de corigență per-elev (spec §2.5 / #33), generată AUTOMAT la marcarea statutului „corigent":
 * disciplina restantă + (după programarea sesiunii) data și comisia. Vizibilă părintelui și dirigintelui.
 *
 * @property CorigentaSeason $season
 * @property Carbon|null $scheduled_on
 * @property bool|null $passed
 */
class CorigentaExam extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'student_id',
        'subject_id',
        'term_id',
        'season',
        'corigenta_session_id',
        'exam_commission_id',
        'scheduled_on',
        'passed',
    ];

    protected function casts(): array
    {
        return [
            'season' => CorigentaSeason::class,
            'scheduled_on' => 'date',
            'passed' => 'boolean',
        ];
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

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<CorigentaSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CorigentaSession::class, 'corigenta_session_id');
    }

    /** @return BelongsTo<ExamCommission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(ExamCommission::class, 'exam_commission_id');
    }
}
