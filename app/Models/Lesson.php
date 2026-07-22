<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Observers\LessonObserver;
use App\Support\WeeklySchedule;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Un slot din orarul structurat al unei clase (spec §2.1): o disciplină, ținută de un profesor,
 * într-o zi + nr. de lecție, opțional într-o sală. Vezi {@see WeeklySchedule}.
 *
 * FĂRĂ SoftDeletes, deliberat. Slotul e configurare, nu act academic: nicio tabelă nu-l referă
 * (notele și absențele se leagă de disciplină, nu de lecție), deci ștergerea lui nu rupe nimic și
 * nu are ce istoric să păstreze. În schimb, soft-delete-ul îl rupea activ: indexul unic pe
 * (clasă, an, zi, nr. lecție) NU cuprinde `deleted_at`, iar panoul n-a avut niciodată acțiune de
 * restaurare sau de golire. Un slot șters rămânea deci în tabel, ocupa pentru totdeauna poziția lui
 * în orar, și orice încercare de a-l recrea cădea în eroare de constrângere — fără nicio cale de
 * ieșire din interfață.
 *
 * @property Weekday $day_of_week
 * @property int $lesson_number
 */
#[ObservedBy(LessonObserver::class)]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /**
     * Gărzi ABSOLUTE de consistență (standardizarea 2026-07-21), sub ORICE cale de scriere prin
     * model: numărul lecției rămâne în plaja zilei (1–8), un slot (clasă, zi, nr.) nu se poate
     * dubla (altfel indexul unic ieșea ca eroare de constrângere — pagină de eroare, nu mesaj),
     * iar orarul unei clase dintr-un an ÎNCHIS e structură arhivată: nu se mai scrie și nu se mai
     * șterge. Importul legacy prin query builder rămâne deliberat în afara gărzilor.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $lesson): void {
            $number = $lesson->getAttribute('lesson_number');

            if ($number !== null && (! is_numeric($number) || (int) $number < 1 || (int) $number > 8)) {
                throw ValidationException::withMessages([
                    'lesson_number' => __('panel.validation.lesson.number_out_of_range'),
                ]);
            }

            $classId = $lesson->getAttribute('school_class_id');
            $day = $lesson->getAttribute('day_of_week');

            if ($classId !== null && $day !== null && $number !== null) {
                $duplicate = self::query()
                    ->where('school_class_id', $classId)
                    ->where('day_of_week', $day)
                    ->where('lesson_number', (int) $number)
                    ->when($lesson->exists, fn ($query) => $query->whereKeyNot($lesson->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'lesson_number' => __('panel.forms.lesson.slot_taken'),
                    ]);
                }
            }

            if (self::classYearClosed($classId) || ($lesson->isDirty('school_class_id') && self::classYearClosed($lesson->getOriginal('school_class_id')))) {
                throw ValidationException::withMessages([
                    'school_class_id' => __('panel.validation.lesson.class_year_closed'),
                ]);
            }
        });

        static::deleting(static function (self $lesson): void {
            if (self::classYearClosed($lesson->getAttribute('school_class_id'))) {
                throw ValidationException::withMessages([
                    'school_class_id' => __('panel.validation.lesson.class_year_closed'),
                ]);
            }
        });
    }

    /** Anul școlar al clasei date e închis (structură arhivată)? */
    private static function classYearClosed(mixed $classId): bool
    {
        if (! is_numeric($classId)) {
            return false;
        }

        return SchoolClass::query()
            ->whereKey((int) $classId)
            ->whereHas('academicYear', fn ($query) => $query->whereNotNull('closed_at'))
            ->exists();
    }

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
