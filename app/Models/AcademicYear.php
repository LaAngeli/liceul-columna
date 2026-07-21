<?php

namespace App\Models;

use App\Filament\Concerns\RejectsClosedYearWrites;
use App\Models\Concerns\EnsuresSingleCurrent;
use App\Support\SchoolCalendar;
use Database\Factories\AcademicYearFactory;
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
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 */
class AcademicYear extends Model implements Auditable
{
    // Anul școlar: deschiderea/închiderea (closed_at) guvernează scrierile întregului catalog — jurnalizat.
    use AuditableTrait;

    /** @use HasFactory<AcademicYearFactory> */
    use EnsuresSingleCurrent, HasFactory, SoftDeletes;

    /**
     * Gărzi ABSOLUTE de consistență (standardizarea 2026-07-21), sub ORICE cale de model:
     * denumirea respectă FORMATUL CANONIC „2026–2027" (doi ani calendaristici CONSECUTIVI,
     * cu cratimă en-dash — convenția tuturor datelor reale), iar intervalul nu poate fi
     * răsturnat. Apartenența datelor la anii calendaristici ai denumirii se impune în formular
     * (regulă server) — nu aici, ca importul legacy și fixture-urile istorice să rămână valide.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $year): void {
            $name = $year->getAttribute('name');

            if (is_string($name) && $name !== '' && self::startYearFromName($name) === null) {
                throw ValidationException::withMessages([
                    'name' => __('panel.validation.academic_year.name_not_canonical'),
                ]);
            }

            $startsOn = $year->getAttribute('starts_on');
            $endsOn = $year->getAttribute('ends_on');

            if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => __('panel.validation.academic_year.dates_inverted'),
                ]);
            }
        });
    }

    /** Denumirea CANONICĂ a anului școlar care începe în anul calendaristic dat: „2026–2027". */
    public static function canonicalName(int $startYear): string
    {
        return $startYear.'–'.($startYear + 1);
    }

    /**
     * Anul calendaristic de START din denumirea canonică — null când denumirea nu e canonică
     * (format străin sau ani neconsecutivi).
     */
    public static function startYearFromName(?string $name): ?int
    {
        if (! is_string($name) || preg_match('/^(\d{4})–(\d{4})$/u', $name, $m) !== 1) {
            return null;
        }

        return ((int) $m[2] === (int) $m[1] + 1) ? (int) $m[1] : null;
    }

    /**
     * CANDIDAȚII pentru un an școlar nou: următoarele denumiri canonice DISPONIBILE (cei deja
     * definiți — inclusiv arhivați, indexul unic îi vede — sunt excluși), pornind din urmă cu un
     * an față de anul calendaristic curent. Primul candidat = propunerea implicită.
     *
     * @return array<string, string>
     */
    public static function candidateNames(int $count = 6): array
    {
        $existing = self::withTrashed()->pluck('name')->all();
        $base = SchoolCalendar::localNow()->year - 1;

        $candidates = [];

        for ($start = $base; count($candidates) < $count && $start < $base + $count + 20; $start++) {
            $name = self::canonicalName($start);

            if (! in_array($name, $existing, true)) {
                $candidates[$name] = $name;
            }
        }

        return $candidates;
    }

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'is_current',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Anul a fost închis oficial: mediile au trecut în foaia matricolă, iar catalogul lui nu mai
     * primește scrieri (vezi {@see RejectsClosedYearWrites}).
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return HasMany<Term, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    /** @return HasMany<SchoolClass, $this> */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
