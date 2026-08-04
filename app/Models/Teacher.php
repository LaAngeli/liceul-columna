<?php

namespace App\Models;

use App\Enums\Sex;
use App\Enums\UserRole;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Sex|null $sex
 */
class Teacher extends Model implements Auditable
{
    // Fișa profesorului (registru de personal): modificările jurnalizate (L133 §7).
    use AuditableTrait;

    /** @use HasFactory<TeacherFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'sex',
        'email',
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
     * Disciplinele care se predau la clasele lui de DIRIGENȚIE — indiferent de titular.
     *
     * Dirigintele răspunde de clasă în întregime, deci o vede pe toată: spec §3.2 îi dă
     * „vizualizare completă (citire) pe toate disciplinele clasei sale". De aceea lista NU se
     * filtrează pe `teacher_id` — tocmai disciplinele COLEGILOR sunt cele care lipseau.
     *
     * @return list<int>
     */
    public function homeroomSubjectIds(): array
    {
        $classIds = $this->homeroomSchoolClassIds();

        if ($classIds === []) {
            return [];
        }

        return array_values(
            TeachingAssignment::query()
                ->whereIn('school_class_id', $classIds)
                ->pluck('subject_id')
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->all()
        );
    }

    /**
     * Disciplinele vizibile în CONTEXTUL pedagogic dat — perechea lui {@see contextSchoolClassIds}.
     *
     * Profesor → doar cele predate de el; Diriginte → toate disciplinele claselor lui; fără context
     * (mono-rol) → reuniunea. Sursă unică: înainte, fiecare suprafață chema direct
     * `taughtSubjectIds()`, iar dirigintele își pierdea clasa pe drum — vedea în „Discipline" și în
     * filtrele de rapoarte doar ce preda el însuși.
     *
     * @return list<int>
     */
    public function contextSubjectIds(?UserRole $context): array
    {
        return match ($context) {
            UserRole::Profesor => $this->taughtSubjectIds(),
            UserRole::Diriginte => $this->homeroomSubjectIds(),
            default => array_values(array_unique([
                ...$this->taughtSubjectIds(),
                ...$this->homeroomSubjectIds(),
            ])),
        };
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
     * Clasele vizibile în CONTEXTUL pedagogic dat (multi-rol F3, doc pct. 5):
     * Profesor → clasele PREDATE (inclusiv cea de dirigenție dacă predă acolo — dar cu drepturi
     * de profesor); Diriginte → EXCLUSIV clasele de dirigenție; null (mono-rol / administrație)
     * → reuniunea istorică. Sursa unică a separării — nu re-implementa pe la resurse.
     *
     * @return list<int>
     */
    public function contextSchoolClassIds(?UserRole $context): array
    {
        return match ($context) {
            UserRole::Profesor => $this->taughtSchoolClassIds(),
            UserRole::Diriginte => $this->homeroomSchoolClassIds(),
            default => $this->visibleSchoolClassIds(),
        };
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
