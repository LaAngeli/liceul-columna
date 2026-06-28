<?php

namespace App\Models;

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Sesiune de lichidare a corigenței (spec §2.5 / #33): propusă de vicedirectorul pe instruire (draft)
 * → aprobată prin ordinul directorului → publicată de administratorul operațional (vizibilă familiilor).
 *
 * @property CorigentaSeason $season
 * @property CorigentaSessionType $type
 * @property CorigentaSessionStatus $status
 * @property string|null $order_reference
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class CorigentaSession extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'academic_year_id',
        'season',
        'type',
        'starts_on',
        'ends_on',
        'status',
        'order_reference',
        'proposed_by_user_id',
        'approved_by_user_id',
        'published_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'season' => CorigentaSeason::class,
            'type' => CorigentaSessionType::class,
            'status' => CorigentaSessionStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === CorigentaSessionStatus::Published;
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<CorigentaExam, $this> */
    public function exams(): HasMany
    {
        return $this->hasMany(CorigentaExam::class);
    }
}
