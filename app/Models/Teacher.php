<?php

namespace App\Models;

use App\Enums\Sex;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Sex|null $sex
 */
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'sex',
        'email',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
        ];
    }

    /**
     * Numele complet (nume + prenume).
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->last_name} {$this->first_name}"));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clasele la care e diriginte.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /**
     * Clasele la care PREDĂ (din repartizări) — acces limitat la disciplinele lui.
     *
     * @return list<int>
     */
    public function taughtSchoolClassIds(): array
    {
        return array_values(
            $this->teachingAssignments()->pluck('school_class_id')
                ->map(static fn ($id): int => (int) $id)->unique()->all()
        );
    }

    /**
     * Disciplinele pe care le predă (din repartizări).
     *
     * @return list<int>
     */
    public function taughtSubjectIds(): array
    {
        return array_values(
            $this->teachingAssignments()->pluck('subject_id')
                ->map(static fn ($id): int => (int) $id)->unique()->all()
        );
    }

    /**
     * Clasele la care e DIRIGINTE — acces complet la toată clasa (orice disciplină).
     *
     * @return list<int>
     */
    public function homeroomSchoolClassIds(): array
    {
        return array_values(
            $this->homeroomClasses()->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all()
        );
    }

    /**
     * Are dreptul să pună/editeze NOTE la (clasă, disciplină)? Doar dacă predă efectiv
     * acea disciplină la acea clasă (dirigintele VEDE tot, dar notează doar la disciplina lui).
     */
    public function canGradeClassSubject(int $schoolClassId, int $subjectId): bool
    {
        return $this->teachingAssignments()
            ->where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /**
     * Are dreptul să înregistreze ABSENȚE pentru (clasă, disciplină)? Profesorul doar la
     * disciplina lui; dirigintele pentru orice disciplină din clasa lui.
     */
    public function canRecordAbsence(int $schoolClassId, ?int $subjectId): bool
    {
        if (in_array($schoolClassId, $this->homeroomSchoolClassIds(), true)) {
            return true;
        }

        return $subjectId !== null && $this->canGradeClassSubject($schoolClassId, $subjectId);
    }

    /**
     * Toate clasele vizibile profesorului/dirigintelui (predate + cele ca diriginte).
     *
     * @return list<int>
     */
    public function visibleSchoolClassIds(): array
    {
        return array_values(array_unique([
            ...$this->taughtSchoolClassIds(),
            ...$this->homeroomSchoolClassIds(),
        ]));
    }
}
