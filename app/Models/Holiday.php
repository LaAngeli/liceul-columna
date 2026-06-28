<?php

namespace App\Models;

use App\Support\Holidays;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zi liberă / vacanță (sursă unică a „zilei nelucrătoare"). O singură zi (ends_on null) sau interval.
 * La orice modificare invalidează cache-ul de date din {@see Holidays}.
 *
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
class Holiday extends Model
{
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
        $flush = static fn (): null => Holidays::flush();

        static::saved($flush);
        static::deleted($flush);
    }
}
