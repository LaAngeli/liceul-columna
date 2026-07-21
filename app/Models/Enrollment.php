<?php

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Înmatricularea (elev × clasă × an) — rândul de REGISTRU care atestă apartenența școlară a
 * unui minor. AUDITABLE (L133 §7): înscrierea, transferul între clase (school_class_id vechi→nou)
 * și marcarea plecării trebuie să fie reconstruibile — cine, când, ce s-a schimbat.
 *
 * @property int $student_id
 * @property int $school_class_id
 * @property int $academic_year_id
 * @property Carbon|null $enrolled_on
 * @property Carbon|null $left_on
 */
class Enrollment extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'school_class_id',
        'academic_year_id',
        'enrolled_on',
        'left_on',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'left_on' => 'date',
        ];
    }

    /**
     * Garda de ștergere stă LÂNGĂ model (ca la semestre): un rând de registru al unui an în care
     * elevul ARE istoric academic nu se șterge pe nicio cale — ar rămâne note/absențe într-un an
     * în care elevul „n-a fost niciodată înmatriculat" (falsificare de registru), iar soft-delete-ul
     * l-ar scoate din „clasa curentă" (scoping diriginte, cabinet). Plecarea = `left_on`, nu ștergere.
     * Rândurile create din greșeală (fără istoric) rămân curățabile.
     */
    protected static function booted(): void
    {
        static::deleting(static function (self $enrollment): void {
            if ($enrollment->hasAcademicHistory()) {
                throw ValidationException::withMessages([
                    'enrollment' => __('panel.validation.enrollment.delete_with_history'),
                ]);
            }
        });
    }

    /**
     * Elevul are istoric academic în ANUL acestei înmatriculări (note sau absențe în semestrele
     * lui — indiferent de clasă: transferul schimbă clasa, dar registrul anului rămâne). Sursă
     * unică pentru garda de model, policy (delete/forceDelete) și semnalele paginii.
     */
    public function hasAcademicHistory(): bool
    {
        $termIds = Term::withTrashed()
            ->where('academic_year_id', $this->academic_year_id)
            ->pluck('id');

        if ($termIds->isEmpty()) {
            return false;
        }

        return Grade::withTrashed()
            ->where('student_id', $this->student_id)
            ->whereIn('term_id', $termIds)
            ->exists()
            || Absence::withTrashed()
                ->where('student_id', $this->student_id)
                ->whereIn('term_id', $termIds)
                ->exists();
    }

    // Relații cu `withTrashed()`: înmatricularea e ISTORIC — arhivarea (soft-delete) unei clase
    // sau a unui elev nu lasă înmatricularea cu părinți null (ex. CorigentaExamObserver citește
    // treapta din schoolClass; cabinetul afișează clasa istorică).

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class)->withTrashed();
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class)->withTrashed();
    }
}
