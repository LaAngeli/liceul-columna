<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Support\Timetable;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un slot din orarul structurat al unei clase (spec §2.1): o disciplină, ținută de un profesor,
 * într-o zi + nr. de lecție, opțional într-o sală. Vezi {@see Timetable}.
 *
 * @property Weekday $day_of_week
 * @property int $lesson_number
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'school_class_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'lesson_number',
        'room',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
            'lesson_number' => 'integer',
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
