<?php

namespace App\Models;

use App\Enums\ScheduleType;
use App\Observers\SchoolClassObserver;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @method static Builder<SchoolClass> withoutHomeroom()
 */
#[ObservedBy(SchoolClassObserver::class)]
class SchoolClass extends Model implements Auditable
{
    // Clasa: schimbarea dirigintelui mută drepturi de validare; redenumirile ating toate afișările — jurnalizat.
    use AuditableTrait;

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
     * Clase ACTIVE (cu cel puțin o înmatriculare) care nu au diriginte FUNCȚIONAL.
     * Sursă UNICĂ pentru cardul „Clase fără diriginte" (DirectorOverview), widget-ul acționabil
     * (ClassesNeedingHomeroom) și filtrul aliniat din tabelul de clase.
     *
     * `whereDoesntHave` acoperă AMBELE goluri: FK null ȘI dirigintele cu fișa ARHIVATĂ (relația
     * exclude soft-deleted) — altfel arhivarea fișei profesorului lăsa clasa fără diriginte real,
     * dar invizibilă în radar (FK-ul rămâne setat spre fișa arhivată).
     *
     * @param  Builder<SchoolClass>  $query
     */
    public function scopeWithoutHomeroom(Builder $query): void
    {
        $query->whereDoesntHave('homeroomTeacher')->has('enrollments');
    }
}
