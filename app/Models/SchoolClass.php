<?php

namespace App\Models;

use App\Enums\ScheduleType;
use App\Enums\SchoolCycle;
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
use Illuminate\Validation\ValidationException;
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

    /**
     * Gărzi ABSOLUTE de consistență (standardizarea 2026-07-21), sub ORICE cale de model:
     * treapta doar I–XII; secția normalizată (spații, MAJUSCULE — istoricul avea „w1"/„a7");
     * numele ține pasul cu treapta CÂT TIMP e cel canonic (cifra romană a treptei — convenția
     * tuturor claselor reale; clasele „V" pe treapta 9 de pe anii-fantomă au nume custom și NU
     * sunt atinse); clasele noi nu se nasc în ani ÎNCHIȘI. Importul legacy + zona demo scriu
     * prin query builder — deliberat neatinse.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $class): void {
            $grade = $class->getAttribute('grade_level');

            if ($grade !== null && ((int) $grade < SchoolCycle::MIN_GRADE_LEVEL || (int) $grade > SchoolCycle::MAX_GRADE_LEVEL)) {
                throw ValidationException::withMessages([
                    'grade_level' => __('panel.validation.school_class.grade_out_of_structure'),
                ]);
            }

            $section = $class->getAttribute('section');

            if (is_string($section)) {
                $normalized = mb_strtoupper(trim($section));
                $class->setAttribute('section', $normalized === '' ? null : $normalized);
            }

            if ($grade !== null) {
                $name = $class->getAttribute('name');
                $canonicalForOldGrade = SchoolCycle::romanNumeral((int) $class->getOriginal('grade_level'));

                if (! is_string($name) || trim($name) === '' || $name === $canonicalForOldGrade) {
                    $class->setAttribute('name', SchoolCycle::romanNumeral((int) $grade));
                }
            }
        });

        static::creating(static function (self $class): void {
            $closedAt = AcademicYear::query()->whereKey($class->getAttribute('academic_year_id'))->value('closed_at');

            if ($closedAt !== null) {
                throw ValidationException::withMessages([
                    'academic_year_id' => __('panel.validation.school_class.year_closed'),
                ]);
            }
        });
    }

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
