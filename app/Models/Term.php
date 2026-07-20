<?php

namespace App\Models;

use App\Models\Concerns\EnsuresSingleCurrent;
use App\Observers\TermObserver;
use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $academic_year_id
 * @property int $number
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 */
#[ObservedBy(TermObserver::class)]
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use EnsuresSingleCurrent, HasFactory, SoftDeletes;

    /**
     * Gărzile de ștergere stau LÂNGĂ model (ca invariantul corecțiilor pending): nicio cale —
     * panou, seeder, API viitor — nu poate șterge un semestru care ar lăsa sistemul fără reper.
     *
     *  - semestrul CURENT: tot codul presupune exact un curent ({@see EnsuresSingleCurrent});
     *    ștergerea lui ar lăsa derivarea semestrului și filtrele fără fallback;
     *  - semestrul cu ISTORIC academic: notele/absențele/mediile lui ar rămâne legate de un rând
     *    invizibil (soft-delete iese din Term::forDate și din afișări) — §1, istoricul nu dispare;
     *  - semestrul unui an ÎNCHIS: structura anului arhivat e înghețată ({@see AcademicYear::isClosed}).
     */
    protected static function booted(): void
    {
        static::deleting(static function (self $term): void {
            if ($term->is_current) {
                throw ValidationException::withMessages([
                    'term' => __('panel.validation.term.delete_current'),
                ]);
            }

            if ($term->academicYear()->withTrashed()->first()?->isClosed() ?? false) {
                throw ValidationException::withMessages([
                    'term' => __('panel.validation.term.year_closed'),
                ]);
            }

            if ($term->hasAcademicHistory()) {
                throw ValidationException::withMessages([
                    'term' => __('panel.validation.term.delete_with_history'),
                ]);
            }
        });
    }

    /**
     * Semestrul are istoric academic dependent (FK-uri cu `cascadeOnDelete` pe `term_id`): note,
     * absențe, medii, examene de corigență, validări de semestru, confirmări de statut. Lista
     * acoperă TOATE tabelele care cascadează pe `term_id` (derivate din schemă). `withTrashed()`
     * DOAR pe modelele cu SoftDeletes. Sursă unică pentru policy (forceDelete) și pagina Semestre.
     */
    public function hasAcademicHistory(): bool
    {
        $termId = $this->getKey();

        return Grade::withTrashed()->where('term_id', $termId)->exists()
            || Absence::withTrashed()->where('term_id', $termId)->exists()
            || TermAverage::withTrashed()->where('term_id', $termId)->exists()
            || CorigentaExam::query()->where('term_id', $termId)->exists()
            || SemesterValidation::query()->where('term_id', $termId)->exists()
            || StatusAcknowledgement::query()->where('term_id', $termId)->exists();
    }

    protected $fillable = [
        'academic_year_id',
        'number',
        'name',
        'starts_on',
        'ends_on',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Semestrul care conține data dată (sursă unică: absențe, note, import, API). Întoarce null când
     * data nu cade în niciun interval definit (ex. vacanță, sau semestre fără interval) → apelantul
     * decide fallback-ul (de regulă semestrul curent).
     */
    public static function forDate(\DateTimeInterface $date): ?self
    {
        return static::query()
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderByDesc('starts_on')
            ->first();
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<Absence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /** @return HasMany<TermAverage, $this> */
    public function termAverages(): HasMany
    {
        return $this->hasMany(TermAverage::class);
    }
}
