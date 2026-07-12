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

/**
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 */
#[ObservedBy(TermObserver::class)]
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use EnsuresSingleCurrent, HasFactory, SoftDeletes;

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
