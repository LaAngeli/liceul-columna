<?php

namespace App\Models;

use App\Enums\AcademicRecordPeriod;
use Database\Factories\AcademicRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Foaie matricolă: media unui elev pentru o treaptă (clasa 1-12) și perioadă
 * (semestrul I/II ori media anuală), la o disciplină. Arhivă istorică.
 *
 * @property int $grade_level
 * @property AcademicRecordPeriod $period
 * @property numeric-string|null $value
 * @property string|null $calificativ
 */
class AcademicRecord extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<AcademicRecordFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'subject_id',
        'grade_level',
        'period',
        'value',
        'calificativ',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'period' => AcademicRecordPeriod::class,
            'value' => 'decimal:2',
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
}
