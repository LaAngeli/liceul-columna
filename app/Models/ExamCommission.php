<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Comisie de examen pentru lichidarea corigenței (spec §2.5): pe disciplină, cu președinte + membri.
 * Propusă de vicedirectorul pe instruire, folosită la sloturile sesiunilor de corigență.
 */
class ExamCommission extends Model
{
    protected $fillable = [
        'academic_year_id',
        'subject_id',
        'name',
        'president_teacher_id',
    ];

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function president(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'president_teacher_id');
    }

    /** @return BelongsToMany<Teacher, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'exam_commission_teacher');
    }
}
