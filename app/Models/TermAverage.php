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
     * Disciplină restantă (corigent) după regula pe componente (§1.3): dacă există și MC, și
     * sumativă, FIECARE trebuie ≥ 5,00 — o sumativă < 5 nu se compensează cu un MC mare. Cu o
     * singură componentă (primar / disciplină fără sumativă), decide media semestrială (MS).
     */
    public function isFailing(): bool
    {
        $mc = $this->mc_value !== null ? (float) $this->mc_value : null;
        $summative = $this->summative_value !== null ? (float) $this->summative_value : null;

        if ($mc !== null && $summative !== null) {
            return $mc < Grades::PASS || $summative < Grades::PASS;
        }

        return $this->value !== null && (float) $this->value < Grades::PASS;
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
}
