<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static Builder<SchoolClass> withoutHomeroom()
 */
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'grade_level',
        'name',
        'section',
        'homeroom_teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Dirigintele clasei.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<SummativeDesignation, $this> */
    public function summativeDesignations(): HasMany
    {
        return $this->hasMany(SummativeDesignation::class);
    }

    /**
     * Orarul „lecții" publicabil al clasei (legat prin canonizare). Permite cabinetului să refere
     * orarul public al clasei elevului fără a depinde de eticheta-text.
     *
     * @return HasOne<Schedule, $this>
     */
    public function lessonsSchedule(): HasOne
    {
        return $this->hasOne(Schedule::class)->where('type', ScheduleType::Lessons->value);
    }

    /**
     * Clase ACTIVE (cu cel puțin o înmatriculare) care nu au diriginte alocat.
     * Sursă UNICĂ pentru cardul „Clase fără diriginte" (DirectorOverview), widget-ul acționabil
     * (ClassesNeedingHomeroom) și filtrul aliniat din tabelul de clase.
     *
     * @param  Builder<SchoolClass>  $query
     */
    public function scopeWithoutHomeroom(Builder $query): void
    {
        $query->whereNull('homeroom_teacher_id')->has('enrollments');
    }
}
