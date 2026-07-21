<?php

namespace App\Models;

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    /**
     * Gărzi ABSOLUTE de consistență (standardizarea 2026-07-21), sub ORICE cale de scriere prin
     * model: intervalul nu poate fi răsturnat; un an nu poate avea DOUĂ sesiuni cu aceeași
     * combinație (sezon, tip); sesiunile aceluiași an nu se pot SUPRAPUNE în timp (comisiile și
     * profesorii sunt aceiași — două sesiuni simultane nu au sens operațional); un an ÎNCHIS nu
     * mai primește sesiuni. Istoricul neatins e tolerat: dublura/suprapunerea se verifică doar la
     * creare sau când chiar se schimbă combinația/intervalul.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $session): void {
            $startsOn = $session->getAttribute('starts_on');
            $endsOn = $session->getAttribute('ends_on');

            if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => __('panel.validation.corigenta_session.dates_inverted'),
                ]);
            }

            $comboChanged = ! $session->exists
                || $session->isDirty('academic_year_id')
                || $session->isDirty('season')
                || $session->isDirty('type');

            if ($comboChanged && self::duplicateComboExists($session)) {
                throw ValidationException::withMessages([
                    'type' => __('panel.validation.corigenta_session.duplicate'),
                ]);
            }

            $intervalChanged = ! $session->exists
                || $session->isDirty('academic_year_id')
                || $session->isDirty('starts_on')
                || $session->isDirty('ends_on');

            if ($intervalChanged && self::overlapExists($session)) {
                throw ValidationException::withMessages([
                    'ends_on' => __('panel.validation.corigenta_session.overlap_short'),
                ]);
            }

            if ((! $session->exists || $session->isDirty('academic_year_id'))
                && AcademicYear::query()->whereKey($session->getAttribute('academic_year_id'))->whereNotNull('closed_at')->exists()) {
                throw ValidationException::withMessages([
                    'academic_year_id' => __('panel.validation.corigenta_session.year_closed'),
                ]);
            }
        });
    }

    private static function duplicateComboExists(self $session): bool
    {
        $yearId = $session->getAttribute('academic_year_id');
        $season = $session->getAttribute('season');
        $type = $session->getAttribute('type');

        if ($yearId === null || $season === null || $type === null) {
            return false;
        }

        return self::query()
            ->where('academic_year_id', $yearId)
            ->where('season', $season)
            ->where('type', $type)
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()))
            ->exists();
    }

    private static function overlapExists(self $session): bool
    {
        $yearId = $session->getAttribute('academic_year_id');
        $startsOn = $session->getAttribute('starts_on');
        $endsOn = $session->getAttribute('ends_on');

        if ($yearId === null || $startsOn === null || $endsOn === null) {
            return false;
        }

        return self::query()
            ->where('academic_year_id', $yearId)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()))
            ->exists();
    }

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
