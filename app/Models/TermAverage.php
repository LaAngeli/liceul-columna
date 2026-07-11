<?php

namespace App\Models;

use App\Support\Grades;
use Database\Factories\TermAverageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class TermAverage extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<TermAverageFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_class_id',
        'term_id',
        'type',
        'value',
        'mc_value',
        'summative_value',
        'calificativ',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'value' => 'decimal:2',
            'mc_value' => 'decimal:2',
            'summative_value' => 'decimal:2',
        ];
    }

    /**
     * Disciplină restantă (corigent): decizia se ia pe MEDIA SEMESTRIALĂ per disciplină (§3) —
     * MS < 5,00 → corigent. Componentele (MC, sumativă) NU au prag individual: notele se mediază
     * (o notă mică într-o zi nu contează separat), iar promovarea se decide pe media rezultată.
     */
    public function isFailing(): bool
    {
        return $this->value !== null && (float) $this->value < Grades::PASS;
    }

    // Relații cu `withTrashed()`: media semestrială e ISTORIC — arhivarea nomenclatoarelor nu
    // lasă rândurile vechi cu părinți null (aceeași regulă ca la Grade/Absence).

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class)->withTrashed();
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class)->withTrashed();
    }
}
