<?php

namespace App\Models;

use App\Actions\RecomputeMotivationDeadlines;
use App\Support\Holidays;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Zi liberă / vacanță (sursă unică a „zilei nelucrătoare"). O singură zi (ends_on null) sau interval.
 * La orice modificare invalidează cache-ul de date din {@see Holidays} și RECALCULEAZĂ termenele
 * de motivare încă deschise (termenul e snapshot pe calendarul de la creare — vezi
 * {@see RecomputeMotivationDeadlines}). AUDITABLE: zilele libere guvernează termene LEGALE (§2.1) —
 * cine/când le-a schimbat trebuie să fie reconstruibil (L133), altfel două absențe-surori cu
 * termene diferite ar fi nedistinse de o manipulare.
 *
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
class Holiday extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
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
