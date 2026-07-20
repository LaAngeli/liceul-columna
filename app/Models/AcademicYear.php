<?php

namespace App\Models;

use App\Filament\Concerns\RejectsClosedYearWrites;
use App\Models\Concerns\EnsuresSingleCurrent;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 */
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use EnsuresSingleCurrent, HasFactory, SoftDeletes;

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
