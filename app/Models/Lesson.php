<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Observers\LessonObserver;
use App\Support\Timetable;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un slot din orarul structurat al unei clase (spec §2.1): o disciplină, ținută de un profesor,
 * într-o zi + nr. de lecție, opțional într-o sală. Vezi {@see Timetable}.
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
