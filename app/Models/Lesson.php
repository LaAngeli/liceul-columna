<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Observers\LessonObserver;
use App\Support\WeeklySchedule;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string $student_group grupa („1", „2"); șirul gol = toată clasa
 * @property string|null $title eticheta slotului fără disciplină din nomenclator
 * @property string|null $teacher_name numele afișat când persoana nu are fișă
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
                    // Slotul e ocupat doar pentru ACEEAȘI grupă: două grupe ale clasei pot avea
                    // discipline diferite în același interval (orarele reale o fac la limbi străine).
                    ->where('student_group', (string) ($lesson->getAttribute('student_group') ?? ''))
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
        'student_group',
        'title',
        'teacher_id',
        'teacher_name',
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

    /**
     * Grupa are ca „fără grupă" șirul GOL, nu null — santinela indexului unic (în MySQL, două
     * NULL-uri sunt distincte, deci unicitatea slotului s-ar pierde). Un câmp de formular lăsat
     * necompletat trimite însă null, ca orice text gol; normalizarea stă pe model, ca să prindă
     * toate căile de scriere, nu doar formularul.
     *
     * @return Attribute<string, string>
     */
    protected function studentGroup(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => trim((string) ($value ?? '')),
        );
    }

    /**
     * Cum se numește slotul în orar. `title` PRIMEAZĂ asupra numelui din nomenclator, pentru că
     * poartă denumirea sub care ora e cunoscută în școală: orarele scriu „Limba germană", fișa se
     * cheamă „Limba străină 2" — a afișa fișa ar spune familiei mai puțin decât știa înainte.
     * Legătura structurată rămâne `subject_id` (calculele merg pe ea); `title` e doar eticheta.
     *
     * Sloturile fără disciplină deloc („Managementul clasei", „Consultații…") sunt ore reale de
     * orar, dar nu intră în calculele pe discipline — de aceea `subject_id` e nullable, nu forțat.
     */
    public function displayTitle(): string
    {
        $title = trim((string) ($this->title ?? ''));

        $subject = $this->subject;

        return $title !== '' ? $title : (string) ($subject !== null ? $subject->name : '');
    }

    /**
     * Profesorul afișat. Ca și la titlu, forma scrisă în orar primează: inițialele prescurtate din
     * orare disting persoane pe care prima literă le confundă („Foghelizang Iu." ≠ „Foghelizang
     * I."). Fișa legată rămâne sursa când slotul e introdus din panou, unde nu există text scris.
     */
    public function displayTeacher(): ?string
    {
        $name = trim((string) ($this->teacher_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $teacher = $this->teacher;

        return $teacher !== null
            ? trim($teacher->last_name.' '.mb_substr($teacher->first_name, 0, 1).'.')
            : null;
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
