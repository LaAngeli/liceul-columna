<?php

namespace App\Models;

use App\Actions\RecomputeMotivationDeadlines;
use App\Enums\HolidayType;
use App\Support\Holidays;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Zi liberă / vacanță (sursă unică a „zilei nelucrătoare"). O singură zi (ends_on null) sau interval,
 * încadrată într-o categorie ({@see HolidayType}). La orice modificare invalidează cache-ul de date
 * din {@see Holidays} și RECALCULEAZĂ termenele de motivare încă deschise (termenul e snapshot pe
 * calendarul de la creare — vezi {@see RecomputeMotivationDeadlines}). AUDITABLE: zilele libere
 * guvernează termene LEGALE (§2.1) — cine/când le-a schimbat trebuie să fie reconstruibil (L133),
 * altfel două absențe-surori cu termene diferite ar fi nedistinse de o manipulare.
 *
 * @property string $name
 * @property HolidayType $type
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $note
 */
class Holiday extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'starts_on',
        'ends_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => HolidayType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** Sfârșitul efectiv: intervalul de o zi n-are ends_on, dar se termină tot când începe. */
    public function effectiveEndsOn(): Carbon
    {
        return Carbon::parse($this->ends_on ?? $this->starts_on);
    }

    /** Lungimea în zile calendaristice (o zi = 1). */
    public function lengthInDays(): int
    {
        return (int) Carbon::parse($this->starts_on)->startOfDay()
            ->diffInDays($this->effectiveEndsOn()->startOfDay()) + 1;
    }

    /**
     * Zilele libere care ating intervalul [from, to] — inclusiv cele care doar îl încalecă parțial
     * (o vacanță de iarnă începută în decembrie aparține și anului care începe în septembrie).
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeOverlappingSpan(Builder $query, Carbon $from, Carbon $to): void
    {
        // DATE(): cast-ul `date` scrie „Y-m-d H:i:s" (formatul modelului); pe SQLite (teste)
        // comparația de STRING „2025-12-25 00:00:00" <= „2025-12-25" ar exclude ziua-limită.
        $query
            ->whereRaw('DATE(starts_on) <= ?', [$to->toDateString()])
            ->whereRaw('DATE(COALESCE(ends_on, starts_on)) >= ?', [$from->toDateString()]);
    }

    protected static function booted(): void
    {
        // Ordinea contează: întâi flush (recalculul citește datele PROASPETE din cache).
        $refresh = static function (): void {
            Holidays::flush();
            app(RecomputeMotivationDeadlines::class)->run();
        };

        static::saved($refresh);
        static::deleted($refresh);
    }
}
