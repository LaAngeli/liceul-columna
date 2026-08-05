<?php

namespace App\Models;

use App\Enums\AbsenceStatus;
use App\Enums\RequestStatus;
use App\Models\Concerns\ScopedToTeachingCapacity;
use App\Observers\AbsenceObserver;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon $occurred_on
 * @property int|null $lesson_number a câta lecție a zilei (1–8); null = ziua, fără oră precizată
 * @property bool|null $is_motivated null = consemnată de profesor, în așteptarea statutului de la diriginte
 * @property Carbon|null $motivation_deadline
 * @property Carbon|null $motivation_locked_at
 */
#[ObservedBy(AbsenceObserver::class)]
class Absence extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<AbsenceFactory> */
    use HasFactory, ScopedToTeachingCapacity, SoftDeletes;

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_class_id',
        'term_id',
        'teacher_id',
        'occurred_on',
        'lesson_number',
        'is_motivated',
        'motivation_deadline',
        'motivation_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'lesson_number' => 'integer',
            'is_motivated' => 'boolean',
            'motivation_deadline' => 'date',
            'motivation_locked_at' => 'datetime',
        ];
    }

    /** Statutul de afișat: fără statut / motivată / nemotivată — vocabular unic pentru UI. */
    public function status(): AbsenceStatus
    {
        return AbsenceStatus::fromMotivated($this->is_motivated);
    }

    /**
     * Absențele consemnate de profesor, încă fără statut — coada de lucru a dirigintelui.
     *
     * @param  Builder<Absence>  $query
     * @return Builder<Absence>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('is_motivated');
    }

    /**
     * Absențele care NU sunt motivate — nemotivate SAU încă fără statut. Necesară pentru că în
     * SQL `is_motivated = false` NU prinde rândurile NULL: oriunde regula e „tot ce nu e încă
     * motivat" (aprobarea unei motivări, termenele familiei), filtrarea pe `false` ar sări tăcut
     * peste absențele proaspăt consemnate.
     *
     * @param  Builder<Absence>  $query
     * @return Builder<Absence>
     */
    public function scopeNotMotivated(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q->where('is_motivated', false)->orWhereNull('is_motivated'));
    }

    /**
     * Elevul are o motivare APROBATĂ care acoperă ziua dată? Sursă pentru recalcularea lui
     * `is_motivated` când data absenței se mută (dovada acoperă o perioadă, nu o absență anume).
     */
    public function hasApprovedMotivationOn(Carbon|string $date): bool
    {
        // Normalizat la ZI înainte de whereDate: un Carbon se leagă în SQL cu tot cu oră
        // („2026-03-10 00:00:00"), iar pe SQLite comparația devine una de STRING cu
        // strftime('%Y-%m-%d') — „2026-03-10" >= „2026-03-10 00:00:00" e FALS, deci granița
        // `period_end` pica exact pe ziua egală și dovada „de o zi" nu acoperea nimic.
        $day = $date instanceof Carbon ? $date->toDateString() : substr($date, 0, 10);

        return AbsenceMotivation::query()
            ->where('student_id', $this->student_id)
            ->where('status', RequestStatus::Approved)
            ->whereDate('period_start', '<=', $day)
            ->whereDate('period_end', '>=', $day)
            ->exists();
    }

    // Relații cu `withTrashed()`: absența e ISTORIC — arhivarea nomenclatoarelor nu lasă
    // înregistrările vechi cu părinți null (aceeași regulă ca la Grade).

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

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }
}
