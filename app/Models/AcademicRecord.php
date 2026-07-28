<?php

namespace App\Models;

use App\Enums\AcademicRecordPeriod;
use App\Models\Concerns\ScopedToTeachingCapacity;
use Database\Factories\AcademicRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    // Relații cu `withTrashed()`: foaia matricolă e ARHIVA oficială — arhivarea unei discipline
    // sau a unui elev nu are voie să lase rândurile matricolei cu părinți null.

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

    /**
     * Aceeași regulă de calitate ca la restul catalogului ({@see ScopedToTeachingCapacity}), dar
     * exprimată ALTFEL: foaia matricolă e arhivă istorică pe TREAPTĂ, nu pe clasă — n-are
     * `school_class_id` de care să se agațe clauza. Apartenența se deduce prin înmatriculările
     * elevului: dirigintele lui vede foaia întreagă; profesorul, doar disciplinele pe care i le
     * predă. Fără asta, orice profesor citea, pe fișă, media la toate disciplinele din toți cei
     * 12 ani — cea mai extinsă formă a scurgerii, fiindcă e istoricul complet al minorului.
     *
     * @param  Builder<AcademicRecord>  $query
     * @return Builder<AcademicRecord>
     */
    public function scopeVisibleToStaff(Builder $query, ?User $user): Builder
    {
        return self::applyStaffVisibility($query, $user);
    }

    /**
     * Varianta apelabilă pe un builder netipat (closure-ul `modifyQueryUsing`) — vezi motivarea
     * din {@see ScopedToTeachingCapacity::applyStaffVisibility()}.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyStaffVisibility(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || $user->isAdministrator()) {
            return $query;
        }

        $teacher = $user->teacher;

        if (! $teacher instanceof Teacher) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($teacher): void {
            $scoped
                // Diriginte al unei clase în care elevul e/a fost înmatriculat → foaia întreagă.
                ->whereExists(function (QueryBuilder $sub) use ($teacher): void {
                    $sub->selectRaw('1')
                        ->from('enrollments as e')
                        ->join('school_classes as c', 'c.id', '=', 'e.school_class_id')
                        ->whereColumn('e.student_id', 'academic_records.student_id')
                        ->where('c.homeroom_teacher_id', $teacher->id)
                        ->whereNull('e.deleted_at')
                        ->whereNull('c.deleted_at');
                })
                // Profesor → doar disciplinele pe care i le predă elevului.
                ->orWhereExists(function (QueryBuilder $sub) use ($teacher): void {
                    $sub->selectRaw('1')
                        ->from('enrollments as e')
                        ->join('teaching_assignments as ta', 'ta.school_class_id', '=', 'e.school_class_id')
                        ->whereColumn('e.student_id', 'academic_records.student_id')
                        ->whereColumn('ta.subject_id', 'academic_records.subject_id')
                        ->where('ta.teacher_id', $teacher->id)
                        ->whereNull('e.deleted_at')
                        ->whereNull('ta.deleted_at');
                });
        });
    }
}
