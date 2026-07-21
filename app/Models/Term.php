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
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $academic_year_id
 * @property int $number
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 */
#[ObservedBy(TermObserver::class)]
class Term extends Model implements Auditable
{
    // Structura anului: mutarea granițelor unui semestru REALINIAZĂ catalogul — cine/când/vechi→nou trebuie reconstruibil.
    use AuditableTrait;

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
        // Gărzi ABSOLUTE de consistență (fluxul ghidat 2026-07-21), sub ORICE cale de model:
        // numărul doar 1–4, intervalul nerăsturnat, denumirea CANONICĂ („Semestrul I/II...")
        // completată automat când lipsește și ținută sincron cât timp e cea canonică — o denumire
        // custom (editată justificat) rămâne. Semestre noi nu se nasc în ani ÎNCHIȘI (dublura
        // gărzii din formular). Importul legacy scrie prin query builder — deliberat neatins.
        static::saving(static function (self $term): void {
            $number = $term->getAttribute('number');

            if ($number !== null && ((int) $number < 1 || (int) $number > 4)) {
                throw ValidationException::withMessages([
                    'number' => __('panel.validation.term.number_out_of_range'),
                ]);
            }

            $startsOn = $term->getAttribute('starts_on');
            $endsOn = $term->getAttribute('ends_on');

            if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => __('panel.validation.term.dates_inverted'),
                ]);
            }

            if ($number !== null) {
                $name = $term->getAttribute('name');
                $canonicalForOldNumber = self::canonicalName((int) $term->getOriginal('number'));

                if (! is_string($name) || trim($name) === '' || $name === $canonicalForOldNumber) {
                    $term->setAttribute('name', self::canonicalName((int) $number) ?? $name);
                }
            }
        });

        static::creating(static function (self $term): void {
            $closedAt = AcademicYear::query()->whereKey($term->getAttribute('academic_year_id'))->value('closed_at');

            if ($closedAt !== null) {
                throw ValidationException::withMessages([
                    'academic_year_id' => __('panel.validation.term.year_closed'),
                ]);
            }
        });

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
     * Denumirea CANONICĂ a semestrului cu numărul dat („Semestrul I/II/III/IV") — sursă unică
     * pentru completarea automată din formular și pentru garda de sincronizare din {@see booted}.
     */
    public static function canonicalName(int $number): ?string
    {
        $numerals = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        return isset($numerals[$number])
            ? (string) __('panel.forms.term.name_suggestion', ['numeral' => $numerals[$number]])
            : null;
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
