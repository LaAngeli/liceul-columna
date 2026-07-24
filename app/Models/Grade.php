<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use App\Enums\EvaluationType;
use App\Observers\GradeObserver;
use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon $graded_on
 * @property EvaluationType $evaluation_type
 * @property numeric-string|null $value
 * @property string|null $calificativ
 * @property Carbon|null $annulled_at
 * @property string|null $annulment_reason
 */
#[ObservedBy(GradeObserver::class)]
class Grade extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<GradeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_class_id',
        'term_id',
        'teacher_id',
        'graded_on',
        'type',
        'evaluation_type',
        'value',
        'calificativ',
        'annulled_at',
        'annulled_by_user_id',
        'annulment_reason',
    ];

    protected function casts(): array
    {
        return [
            'graded_on' => 'date',
            'type' => 'integer',
            'evaluation_type' => EvaluationType::class,
            'value' => 'decimal:2',
            'annulled_at' => 'datetime',
        ];
    }

    /**
     * Nota anulată (void): rămâne în istoric, dar nu contează la medii și nu apare în cabinet.
     */
    public function isAnnulled(): bool
    {
        return $this->annulled_at !== null;
    }

    /**
     * Doar notele active (neanulate).
     *
     * @param  Builder<Grade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('annulled_at');
    }

    /**
     * Nota individuală e un NUMĂR ÎNTREG pe scala 1–10 (§1); zecimalele aparțin exclusiv mediilor
     * ({@see TermAverage}, sutimi fără rotunjire — §2.4).
     *
     * Garda stă pe model, nu doar pe formular, pentru că formularul e o singură cale de intrare:
     * seedere, comenzi și un viitor API scriu tot prin model. Descoperit pe date reale: cele 52.228
     * de note importate din sistemul școlii sunt TOATE întregi, în timp ce două generatoare de
     * demo (`app:seed-demo-zone`, `app:simulate-demo-activity`) produceau valori de tipul 6,5 —
     * ajunse până în cabinetul familiei.
     *
     * Importul legacy scrie prin query builder, deci NU trece pe aici — deliberat: reproduce
     * fidel datele școlii, iar o gardă acolo ar rescrie istoric.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $grade): void {
            $value = $grade->getAttribute('value');

            if ($value !== null && (float) $value !== floor((float) $value)) {
                throw ValidationException::withMessages([
                    'value' => __('panel.validation.grade.value_must_be_integer'),
                ]);
            }
        });
    }

    /**
     * Există deja o cerere de corecție nesoluționată pe această notă? Blochează depunerea unei a
     * doua cereri (o notă nu poate avea două propuneri de valoare în așteptare simultan).
     *
     * Folosește `pending_corrections_count` dacă lista l-a preîncărcat cu `withCount` — altfel
     * fiecare rând de tabel ar declanșa propria interogare.
     */
    public function hasPendingCorrection(): bool
    {
        $preloaded = $this->getAttribute('pending_corrections_count');

        if ($preloaded !== null) {
            return (int) $preloaded > 0;
        }

        return $this->corrections()
            ->where('status', CorrectionStatus::Pending)
            ->exists();
    }

    /** @return HasMany<GradeCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(GradeCorrection::class);
    }

    // NB: relațiile de mai jos poartă `withTrashed()` — nota e ISTORIC (§1): arhivarea (soft-delete)
    // unui elev / a unei discipline / clase / semestru nu are voie să lase istoricul cu părinți null
    // (cabinetul, triajul corigenților și acțiunile de anulare ar crăpa cu „property on null").

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

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class)->withTrashed();
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class)->withTrashed();
    }

    /**
     * Autorul notei.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }
}
